import Dexie from 'dexie';
import type {Table} from 'dexie';

export type OutboxStatus = 'pending' | 'syncing' | 'synced' | 'failed';

export interface OutboxEntry {
    id?: number;
    client_event_id: string;
    manifest_id: string;
    barcode: string;
    occurred_at: string;
    acknowledged_warnings: string[];
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

export async function flushOutbox(callbacks: FlushCallbacks): Promise<void> {
    if (flushing) {
return;
}

    flushing = true;

    try {
        const pending = await db.outbox
            .where('status')
            .anyOf('pending', 'syncing')
            .sortBy('created_at');

        for (const entry of pending) {
            await db.outbox.update(entry.id!, { status: 'syncing' });

            try {
                const response = await postScan(entry);
                const json = (await response.json()) as Record<string, unknown>;

                if (response.ok) {
                    await db.outbox.update(entry.id!, { status: 'synced' });
                    callbacks.onSynced(entry, json);
                } else if (response.status === 409 || response.status === 422) {
                    await db.outbox.update(entry.id!, { status: 'synced' });
                    const errorCode =
                        ((json.error as Record<string, unknown>)?.code as string) ?? '';
                    callbacks.onWarning(entry, errorCode, json);
                } else {
                    await handleFlushError(entry);
                    const message =
                        ((json.error as Record<string, unknown>)?.message as string) ??
                        'Server error';
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
    const xsrfToken = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return fetch(`/loading/manifests/${entry.manifest_id}/scan`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {}),
        },
        body: JSON.stringify({
            barcode: entry.barcode,
            client_event_id: entry.client_event_id,
            occurred_at: entry.occurred_at,
            acknowledged_warnings: entry.acknowledged_warnings,
        }),
    });
}
