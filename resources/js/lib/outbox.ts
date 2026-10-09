import Dexie from 'dexie';
import type { Table } from 'dexie';
import { isAuthFailure, notifyAuthExpired, xsrfToken } from '@/lib/session';

export type OutboxStatus = 'pending' | 'syncing' | 'synced' | 'failed';

export interface OutboxEntry {
    id?: number;
    client_event_id: string;
    manifest_id: string;
    barcode: string;
    occurred_at: string;
    acknowledged_warnings: string[];
    // Whose scan this is; it is only ever sent while that user is signed in.
    user_id?: number;
    status: OutboxStatus;
    retry_count: number;
    created_at: number;
}

export interface FlushCallbacks {
    onSynced: (entry: OutboxEntry, json: unknown) => void;
    onWarning: (entry: OutboxEntry, warningCode: string, json: unknown) => void;
    onError: (entry: OutboxEntry, message: string) => void;
}

class OutboxDb extends Dexie {
    outbox!: Table<OutboxEntry, number>;

    constructor() {
        super('freight-ops-outbox');
        this.version(1).stores({
            outbox: '++id, client_event_id, manifest_id, status, created_at',
        });
    }
}

export const db = new OutboxDb();

export const MAX_RETRIES = 5;
let flushing = false;

/** Entries this user may send: their own, plus any from before users were recorded. */
export function isOwnedBy(entry: OutboxEntry, userId?: number): boolean {
    return (
        userId === undefined ||
        entry.user_id === undefined ||
        entry.user_id === userId
    );
}

export async function flushOutbox(
    callbacks: FlushCallbacks,
    userId?: number,
): Promise<void> {
    if (flushing) {
        return;
    }

    flushing = true;

    try {
        const pending = await db.outbox
            .where('status')
            .anyOf('pending', 'syncing')
            .sortBy('created_at');

        for (const entry of pending.filter((e) => isOwnedBy(e, userId))) {
            await db.outbox.update(entry.id!, { status: 'syncing' });

            try {
                const response = await postScan(entry);

                // The session ended. Keep the scan exactly as it was (no retry
                // is spent), stop, and let the app ask the user to sign back in.
                if (isAuthFailure(response)) {
                    await db.outbox.update(entry.id!, { status: 'pending' });
                    notifyAuthExpired();

                    return;
                }

                const json = (await response.json()) as Record<string, unknown>;

                if (response.ok) {
                    await db.outbox.update(entry.id!, { status: 'synced' });
                    callbacks.onSynced(entry, json);
                } else if (response.status === 409 || response.status === 422) {
                    await db.outbox.update(entry.id!, { status: 'synced' });
                    const errorCode =
                        ((json.error as Record<string, unknown>)
                            ?.code as string) ?? '';
                    callbacks.onWarning(entry, errorCode, json);
                } else {
                    await handleFlushError(entry);
                    const message =
                        ((json.error as Record<string, unknown>)
                            ?.message as string) ?? 'Server error';
                    callbacks.onError(entry, message);
                }
            } catch {
                await handleFlushError(entry);
                callbacks.onError(entry, 'Network error');
            }
        }
    } finally {
        flushing = false;
    }
}

async function handleFlushError(entry: OutboxEntry): Promise<void> {
    const next = entry.retry_count + 1;
    await db.outbox.update(entry.id!, {
        status: next >= MAX_RETRIES ? 'failed' : 'pending',
        retry_count: next,
    });
}

function postScan(entry: OutboxEntry): Promise<Response> {
    const token = xsrfToken();

    return fetch(`/loading/manifests/${entry.manifest_id}/scan`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        },
        body: JSON.stringify({
            barcode: entry.barcode,
            client_event_id: entry.client_event_id,
            occurred_at: entry.occurred_at,
            acknowledged_warnings: entry.acknowledged_warnings,
        }),
    });
}
