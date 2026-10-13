import { useRef, useState } from 'react';
import { getJson, HttpError, postJson } from '@/lib/http';

export type LaneOption = {
    location_id: string;
    code: string;
    lane: number | null;
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
    suggested_lane: number | null;
    alternatives: LaneOption[];
};

type Stage = 'idle' | 'directed' | 'dropped';

type Pending = { clientEventId: string; occurredAt: string };

export function useUnloadRunner() {
    const [barcode, setBarcode] = useState('');
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

    async function confirm() {
        if (!direction || busyRef.current) {
            return;
        }

        pendingRef.current ??= {
            clientEventId: crypto.randomUUID(),
            occurredAt: new Date().toISOString(),
        };

        busyRef.current = true;
        setBusy(true);
        setError('');

        try {
            await postJson('/unload/confirm', {
                barcode: direction.barcode,
                location_id: override?.location_id ?? direction.bay.location_id,
                client_event_id: pendingRef.current.clientEventId,
                occurred_at: pendingRef.current.occurredAt,
            });

            pendingRef.current = null;
            setStage('dropped');
        } catch (confirmError) {
            if (
                confirmError instanceof HttpError &&
                confirmError.code === 'direction_changed'
            ) {
                // The bay changed since the scan: show the new one to confirm.
                busyRef.current = false;
                setBusy(false);
                pendingRef.current = null;
                await lookup(direction.barcode);
                setError(confirmError.message);

                return;
            }

            setError(
                confirmError instanceof HttpError
                    ? confirmError.message
                    : 'No connection. Tap Confirm again once you are back online.',
            );
        } finally {
            busyRef.current = false;
            setBusy(false);
        }
    }

    function reset() {
        pendingRef.current = null;
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
        lookup,
        simulate,
        confirm,
        reset,
    };
}
