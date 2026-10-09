import { useCallback, useEffect, useRef, useState } from 'react';
import { db, flushOutbox } from '@/lib/outbox';
import type { FlushCallbacks, OutboxEntry } from '@/lib/outbox';

export type { FlushCallbacks, OutboxEntry };

export interface OutboxState {
    isOnline: boolean;
    pendingCount: number;
    hasFailed: boolean;
    submit: (
        raw: Omit<OutboxEntry, 'id' | 'status' | 'retry_count' | 'created_at'>,
        callbacks: FlushCallbacks,
    ) => Promise<void>;
}

async function readCounts() {
    const pending = await db.outbox
        .where('status')
        .anyOf('pending', 'syncing')
        .count();
    const failed = await db.outbox.where('status').equals('failed').count();

    return { pending, failed };
}

export function useOutbox(): OutboxState {
    const [isOnline, setIsOnline] = useState(() => navigator.onLine);
    const [pendingCount, setPendingCount] = useState(0);
    const [hasFailed, setHasFailed] = useState(false);
    const callbacksRef = useRef<FlushCallbacks | null>(null);

    const refreshCounts = useCallback(async () => {
        const { pending, failed } = await readCounts();
        setPendingCount(pending);
        setHasFailed(failed > 0);
    }, []);

    useEffect(() => {
        let active = true;

        void readCounts().then(({ pending, failed }) => {
            if (!active) {
                return;
            }

            setPendingCount(pending);
            setHasFailed(failed > 0);
        });

        const handleOnline = () => {
            setIsOnline(true);

            if (callbacksRef.current) {
                void flushOutbox(callbacksRef.current).then(refreshCounts);
            }
        };
        const handleOffline = () => setIsOnline(false);

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        return () => {
            active = false;
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, [refreshCounts]);

    const submit = useCallback(
        async (
            raw: Omit<
                OutboxEntry,
                'id' | 'status' | 'retry_count' | 'created_at'
            >,
            callbacks: FlushCallbacks,
        ) => {
            callbacksRef.current = callbacks;

            await db.outbox.add({
                ...raw,
                status: 'pending',
                retry_count: 0,
                created_at: Date.now(),
            });
            await refreshCounts();

            if (navigator.onLine) {
                await flushOutbox(callbacks);
                await refreshCounts();
            }
        },
        [refreshCounts],
    );

    return { isOnline, pendingCount, hasFailed, submit };
}
