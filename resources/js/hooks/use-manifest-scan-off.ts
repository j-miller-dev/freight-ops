import { useCallback, useRef, useState } from 'react';
import type { Progress } from '@/hooks/use-manifest-scanner';
import { getJson, HttpError, postJson } from '@/lib/http';

export type ScanOffResult = {
    barcode: string;
    connoteNumber: string;
    pieceNumber: number;
    status: string;
    locationCode: string | null;
    progress: Progress;
    consignmentProgress: Progress;
};

type ScanOffResponse = {
    data: {
        barcode: string;
        connote_number: string;
        piece_number: number;
        status: string;
        location: { code: string } | null;
        progress: Progress;
        consignment_progress: Progress;
    };
};

/**
 * Take a pallet off the trailer. Unlike loading this goes straight to the
 * server and is not queued offline: undoing a load is a deliberate action
 * that should never be replayed later against a trailer that has moved on.
 */
export function useManifestScanOff(manifestId: string) {
    const busyRef = useRef(false);
    const [busy, setBusy] = useState(false);
    const [simulating, setSimulating] = useState(false);
    const [error, setError] = useState('');
    const [result, setResult] = useState<ScanOffResult | null>(null);

    async function scanOff(value: string): Promise<boolean> {
        const barcode = value.trim();

        if (!barcode || busyRef.current) {
            return false;
        }

        busyRef.current = true;
        setBusy(true);
        setError('');
        setResult(null);

        try {
            const json = await postJson<ScanOffResponse>(
                `/loading/manifests/${manifestId}/scan-off`,
                {
                    barcode,
                    client_event_id: crypto.randomUUID(),
                    occurred_at: new Date().toISOString(),
                },
            );

            setResult({
                barcode: json.data.barcode,
                connoteNumber: json.data.connote_number,
                pieceNumber: json.data.piece_number,
                status: json.data.status,
                locationCode: json.data.location?.code ?? null,
                progress: json.data.progress,
                consignmentProgress: json.data.consignment_progress,
            });

            return true;
        } catch (failure) {
            setError(
                failure instanceof HttpError
                    ? failure.message
                    : 'Could not reach the server. Scanning off needs a connection.',
            );

            return false;
        } finally {
            busyRef.current = false;
            setBusy(false);
        }
    }

    /** Test aid: ask the server for a pallet that is loaded on this manifest. */
    async function pickLoaded(): Promise<string | null> {
        setSimulating(true);
        setError('');

        try {
            const json = await getJson<{ data: { barcode: string } }>(
                `/loading/manifests/${manifestId}/simulate-scan-off`,
            );

            return json.data.barcode;
        } catch (failure) {
            setError(
                failure instanceof HttpError
                    ? failure.message
                    : 'Could not reach the server.',
            );

            return null;
        } finally {
            setSimulating(false);
        }
    }

    const clear = useCallback(() => {
        setResult(null);
        setError('');
    }, []);

    return { busy, simulating, error, result, scanOff, pickLoaded, clear };
}
