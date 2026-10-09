import { useRef, useState } from 'react';
import { useOutbox } from '@/hooks/use-outbox';

export type Progress = {
    loaded_count: number;
    total_count: number;
};

export type ScanResult = {
    handlingUnitId?: string;
    barcode: string;
    connoteNumber?: string;
    pieceNumber?: number;
    loader?: string;
    scannedAt?: string;
    progress?: Progress;
    consignmentProgress?: Progress;
    dgClass?: string | null;
    unNumber?: string | null;
    properShippingName?: string | null;
    isFood?: boolean;
};

export type WarningCode =
    | 'destination_mismatch'
    | 'consignment_split'
    | 'handling_unit_already_assigned';

type PendingScan = {
    barcode: string;
    clientEventId: string;
    occurredAt: string;
    acknowledgedWarnings: string[];
};

export type WarningPrompt = PendingScan & {
    code: WarningCode;
    message: string;
    details?: Record<string, unknown>;
};

const WARNING_CODES: readonly WarningCode[] = [
    'destination_mismatch',
    'consignment_split',
    'handling_unit_already_assigned',
];

function isWarningCode(code: string): code is WarningCode {
    return (WARNING_CODES as readonly string[]).includes(code);
}

type SyncedResponse = {
    data: {
        handling_unit_id?: string;
        connote_number?: string;
        piece_number?: number;
        dg_class?: string | null;
        un_number?: string | null;
        proper_shipping_name?: string | null;
        is_food?: boolean;
        loader?: { name: string };
        loaded_at?: string;
        progress?: Progress;
        consignment_progress?: Progress;
    };
};

type WarningResponse = {
    error: {
        message?: string;
        details?: Record<string, unknown>;
    };
};

export function useManifestScanner(manifestId: string) {
    const outbox = useOutbox();
    const busyRef = useRef(false);

    const [barcode, setBarcode] = useState('');
    const [scanning, setScanning] = useState(false);
    const [simulating, setSimulating] = useState(false);
    const [error, setError] = useState('');
    const [result, setResult] = useState<ScanResult | null>(null);
    const [warning, setWarning] = useState<WarningPrompt | null>(null);

    async function submitScan(scan: PendingScan) {
        if (!scan.barcode) {
            return;
        }

        busyRef.current = true;
        setScanning(true);
        setError('');
        setResult(null);

        try {
            await outbox.submit(
                {
                    client_event_id: scan.clientEventId,
                    manifest_id: manifestId,
                    barcode: scan.barcode,
                    occurred_at: scan.occurredAt,
                    acknowledged_warnings: scan.acknowledgedWarnings,
                },
                {
                    onSynced(entry, json) {
                        const { data } = json as SyncedResponse;

                        setBarcode('');
                        setWarning(null);
                        setResult({
                            handlingUnitId: data.handling_unit_id,
                            barcode: entry.barcode,
                            connoteNumber: data.connote_number,
                            pieceNumber: data.piece_number,
                            dgClass: data.dg_class,
                            unNumber: data.un_number,
                            properShippingName: data.proper_shipping_name,
                            isFood: data.is_food,
                            loader: data.loader?.name,
                            scannedAt: data.loaded_at,
                            progress: data.progress,
                            consignmentProgress: data.consignment_progress,
                        });
                    },
                    onWarning(entry, code, json) {
                        if (!isWarningCode(code)) {
                            return;
                        }

                        const { error: detail } = json as WarningResponse;

                        setWarning({
                            barcode: entry.barcode,
                            clientEventId: entry.client_event_id,
                            occurredAt: entry.occurred_at,
                            acknowledgedWarnings: entry.acknowledged_warnings,
                            code,
                            message:
                                detail.message ??
                                'This scan needs confirmation.',
                            details: detail.details,
                        });
                    },
                    onError(_entry, message) {
                        setError(message);
                    },
                },
            );
        } finally {
            setScanning(false);
            busyRef.current = false;
        }
    }

    function scan(value: string) {
        const trimmed = value.trim();

        if (!trimmed || busyRef.current) {
            return;
        }

        setBarcode(trimmed);

        void submitScan({
            barcode: trimmed,
            clientEventId: crypto.randomUUID(),
            occurredAt: new Date().toISOString(),
            acknowledgedWarnings: [],
        });
    }

    async function simulate() {
        setSimulating(true);
        setError('');

        try {
            const response = await fetch(
                `/loading/manifests/${manifestId}/simulate-scan`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );
            const json = await response.json();

            if (!response.ok) {
                throw new Error(
                    json.error?.message ?? 'No simulated pallet is available.',
                );
            }

            scan(json.data.barcode);
        } catch (simulationError) {
            setError((simulationError as Error).message);
        } finally {
            setSimulating(false);
        }
    }

    function confirmWarning() {
        if (!warning) {
            return;
        }

        void submitScan({
            ...warning,
            acknowledgedWarnings: [
                ...warning.acknowledgedWarnings,
                warning.code,
            ],
        });
    }

    return {
        outbox,
        barcode,
        setBarcode,
        scanning,
        simulating,
        error,
        result,
        warning,
        scan,
        simulate,
        confirmWarning,
        dismissWarning: () => setWarning(null),
    };
}
