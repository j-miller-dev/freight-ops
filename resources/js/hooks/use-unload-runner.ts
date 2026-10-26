import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { useOutbox } from '@/hooks/use-outbox';
import type { FlushCallbacks } from '@/hooks/use-outbox';
import { getJson, HttpError, postJson } from '@/lib/http';

export type LaneOption = {
    location_id: string;
    code: string;
    lane: number | null;
};

export type ConsignmentProgress = {
    item_count: number;
    in_bay: number;
    held: number;
    all_placed: boolean;
};

export type BayDirection = {
    handling_unit_id: string;
    barcode: string;
    piece_number: number;
    connote_number: string;
    item_count: number;
    sender_name: string | null;
    dg_class: string | null;
    un_number: string | null;
    is_food: boolean;
    current_location_code: string | null;
    bay: {
        location_id: string;
        code: string;
        name: string;
        is_holding: boolean;
        max_hold_days: number | null;
        lane: number | null;
    };
    reason: string;
    progress: ConsignmentProgress;
    suggested_lane: number | null;
    alternatives: LaneOption[];
};

type Stage = 'idle' | 'directed' | 'dropped';

type Pending = { clientEventId: string; occurredAt: string };

export function useUnloadRunner() {
    const [barcode, setBarcode] = useState('');
    // The drop is saved on this device and has not reached the server yet.
    const [queued, setQueued] = useState(false);
    // Drops that synced later but could not be recorded, for a supervisor.
    const [problems, setProblems] = useState<string[]>([]);
    const [stage, setStage] = useState<Stage>('idle');
    const [direction, setDirection] = useState<BayDirection | null>(null);
    const [busy, setBusy] = useState(false);
    const [simulating, setSimulating] = useState(false);
    // Set when the suggested lane is full and the runner picks another.
    const [override, setOverride] = useState<LaneOption | null>(null);
    const [error, setError] = useState('');
    // One event id per drop-off attempt, so tapping Confirm again after a
    // dropped connection can never record the move twice.
    const pendingRef = useRef<Pending | null>(null);
    const busyRef = useRef(false);

    async function lookup(value: string) {
        const trimmed = value.trim();

        if (!trimmed || busyRef.current) {
            return;
        }

        busyRef.current = true;
        setBusy(true);
        setError('');
        setBarcode(trimmed);

        try {
            const { data } = await postJson<{ data: BayDirection }>(
                '/unload/direction',
                { barcode: trimmed },
            );

            pendingRef.current = null;
            setOverride(null);
            setDirection(data);
            setStage('directed');
        } catch (lookupError) {
            setDirection(null);
            setStage('idle');
            setError(
                lookupError instanceof HttpError && lookupError.status === 404
                    ? `No pallet found for ${trimmed}.`
                    : (lookupError as Error).message,
            );
        } finally {
            busyRef.current = false;
            setBusy(false);
        }
    }

    async function simulate() {
        setSimulating(true);
        setError('');

        try {
            const { data } = await getJson<{ data: { barcode: string } }>(
                '/unload/simulate-scan',
            );

            await lookup(data.barcode);
        } catch (simulationError) {
            setError((simulationError as Error).message);
        } finally {
            setSimulating(false);
        }
    }

    // What to do when a confirm's result arrives. The pallet is already down,
    // so a result for an earlier pallet must never disturb the current screen.
    const callbacks: FlushCallbacks = {
        onSynced(entry, json) {
            const { data } = json as { data: BayDirection };

            if (entry.client_event_id === pendingRef.current?.clientEventId) {
                pendingRef.current = null;
                // Counts now include the pallet just dropped.
                setDirection((current) =>
                    current ? { ...current, progress: data.progress } : current,
                );
                setQueued(false);
                setStage('dropped');

                return;
            }

            toast.success(`Drop of ${entry.barcode} saved.`);
        },
        onWarning(entry, code, json) {
            const message =
                (json as { error?: { message?: string } }).error?.message ??
                'The drop could not be recorded.';

            if (entry.client_event_id !== pendingRef.current?.clientEventId) {
                setProblems((current) => [
                    ...current,
                    `${entry.barcode}: ${message}`,
                ]);

                return;
            }

            if (code === 'direction_changed') {
                // The bay changed since the scan: show the new one to confirm.
                pendingRef.current = null;
                void lookup(entry.barcode).then(() => setError(message));

                return;
            }

            pendingRef.current = null;
            setError(message);
        },
        onError() {
            // No connection: the drop stays queued and goes out by itself.
        },
    };
    const outbox = useOutbox('unload_confirm', callbacks);

    async function confirm() {
        if (!direction || busyRef.current) {
            return;
        }

        const drop = (pendingRef.current ??= {
            clientEventId: crypto.randomUUID(),
            occurredAt: new Date().toISOString(),
        });

        busyRef.current = true;
        setBusy(true);
        setError('');

        try {
            await outbox.submit(
                {
                    kind: 'unload_confirm',
                    client_event_id: drop.clientEventId,
                    barcode: direction.barcode,
                    location_id:
                        override?.location_id ?? direction.bay.location_id,
                    occurred_at: drop.occurredAt,
                    acknowledged_warnings: [],
                    queued: !navigator.onLine,
                },
                callbacks,
            );

            // Still waiting after the attempt: no connection. The pallet is
            // down, so the runner moves on and the queue sends it later.
            if (pendingRef.current?.clientEventId === drop.clientEventId) {
                setQueued(true);
                setStage('dropped');
            }
        } finally {
            busyRef.current = false;
            setBusy(false);
        }
    }

    function reset() {
        // A drop still waiting to send keeps its place in the queue.
        pendingRef.current = null;
        setQueued(false);
        setOverride(null);
        setBarcode('');
        setDirection(null);
        setStage('idle');
        setError('');
    }

    return {
        barcode,
        setBarcode,
        stage,
        direction,
        override,
        chooseLane: setOverride,
        busy,
        simulating,
        error,
        queued,
        problems,
        dismissProblems: () => setProblems([]),
        outbox,
        lookup,
        simulate,
        confirm,
        reset,
    };
}
