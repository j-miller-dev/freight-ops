import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { db, flushOutbox, isOwnedBy, kindOf } from '@/lib/outbox';
import type { FlushCallbacks, OutboxEntry, OutboxKind } from '@/lib/outbox';
import { onAuthRestored } from '@/lib/session';

export type { FlushCallbacks, OutboxEntry };

export interface OutboxState {
    isOnline: boolean;
    pendingCount: number;
    hasFailed: boolean;
    submit: (
        raw: Omit<
            OutboxEntry,
            'id' | 'status' | 'retry_count' | 'created_at' | 'user_id'
        >,
        callbacks: FlushCallbacks,
    ) => Promise<void>;
}

// How often something waiting in the queue is tried again while online.
const RETRY_MS = 15_000;

async function readCounts(userId: number, kind: OutboxKind) {
    const mine = (entry: OutboxEntry) =>
        isOwnedBy(entry, userId) && kindOf(entry) === kind;

    const pending = await db.outbox
        .where('status')
        .anyOf('pending', 'syncing')
        .filter(mine)
        .count();
    const failed = await db.outbox
        .where('status')
        .equals('failed')
        .filter(mine)
        .count();

    return { pending, failed };
}

/**
 * The device's queue for one kind of entry. `background` says what to do with
 * a result when it arrives with nobody tapping anything (back online, or an
 * entry left from an earlier visit), so the queue drains on its own.
 */
export function useOutbox(
    kind: OutboxKind = 'scan',
    background?: FlushCallbacks,
): OutboxState {
    const userId = usePage().props.auth.user.id;
    const [isOnline, setIsOnline] = useState(() => navigator.onLine);
    const [pendingCount, setPendingCount] = useState(0);
    const [hasFailed, setHasFailed] = useState(false);
    const callbacksRef = useRef<FlushCallbacks | null>(background ?? null);

    // The latest handlers, so a late result reaches the screen as it is now.
    useEffect(() => {
        if (background) {
            callbacksRef.current = background;
        }
    });

    const refreshCounts = useCallback(async () => {
        const { pending, failed } = await readCounts(userId, kind);
        setPendingCount(pending);
        setHasFailed(failed > 0);
    }, [userId, kind]);

    useEffect(() => {
        let active = true;

        void readCounts(userId, kind).then(({ pending, failed }) => {
            if (!active) {
                return;
            }

            setPendingCount(pending);
            setHasFailed(failed > 0);
        });

        const flush = () => {
            if (callbacksRef.current) {
                void flushOutbox(callbacksRef.current, userId, kind).then(
                    refreshCounts,
                );
            }
        };

        // Left over from an earlier visit, or still waiting after a bad patch
        // of signal: try again now and keep trying while it is waiting.
        if (navigator.onLine) {
            flush();
        }

        const retry = setInterval(() => {
            if (navigator.onLine) {
                flush();
            }
        }, RETRY_MS);

        const handleOnline = () => {
            setIsOnline(true);
            flush();
        };
        const handleOffline = () => setIsOnline(false);

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
        // Signed back in after the session expired: send what was waiting.
        const stopListening = onAuthRestored(flush);

        return () => {
            active = false;
            clearInterval(retry);
            stopListening();
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, [userId, kind, refreshCounts]);

    const submit = useCallback(
        async (
            raw: Omit<
                OutboxEntry,
                'id' | 'status' | 'retry_count' | 'created_at' | 'user_id'
            >,
            callbacks: FlushCallbacks,
        ) => {
            callbacksRef.current = callbacks;

            await db.outbox.add({
                ...raw,
                user_id: userId,
                status: 'pending',
                retry_count: 0,
                created_at: Date.now(),
            });
            await refreshCounts();

            if (navigator.onLine) {
                await flushOutbox(callbacks, userId, kind);
                await refreshCounts();
            }
        },
        [refreshCounts, userId, kind],
    );

    return { isOnline, pendingCount, hasFailed, submit };
}
