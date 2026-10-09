import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { db, flushOutbox, isOwnedBy } from '@/lib/outbox';
import type { FlushCallbacks, OutboxEntry } from '@/lib/outbox';
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

async function readCounts(userId: number) {
    const mine = (entry: OutboxEntry) => isOwnedBy(entry, userId);

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

export function useOutbox(): OutboxState {
    const userId = usePage().props.auth.user.id;
    const [isOnline, setIsOnline] = useState(() => navigator.onLine);
    const [pendingCount, setPendingCount] = useState(0);
    const [hasFailed, setHasFailed] = useState(false);
    const callbacksRef = useRef<FlushCallbacks | null>(null);

    const refreshCounts = useCallback(async () => {
        const { pending, failed } = await readCounts(userId);
        setPendingCount(pending);
        setHasFailed(failed > 0);
    }, [userId]);

    useEffect(() => {
        let active = true;

        void readCounts(userId).then(({ pending, failed }) => {
            if (!active) {
                return;
            }

            setPendingCount(pending);
            setHasFailed(failed > 0);
        });

        const flush = () => {
            if (callbacksRef.current) {
                void flushOutbox(callbacksRef.current, userId).then(
                    refreshCounts,
                );
            }
        };

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
            stopListening();
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, [userId, refreshCounts]);

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
                await flushOutbox(callbacks, userId);
                await refreshCounts();
            }
        },
        [refreshCounts, userId],
    );

    return { isOnline, pendingCount, hasFailed, submit };
}
