import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '@/layouts/app-layout';

type Destination = {
    id: string;
    code: string;
    name: string;
};

type Manifest = {
    id: string;
    manifest_number: string;
    service_date: string;
    status: string;
    manifest_items_count: number;
};

type ScanResult = {
    barcode: string;
    loader?: string;
    scannedAt?: string;
    connote_number?: string;
    piece_number?: number;
    progress?: {
        loaded_count: number;
        total_count: number;
    };
};

type PendingScan = {
    barcode: string;
    clientEventId: string;
    occurredAt: string;
    acknowledgedWarnings: string[];
};

type WarningPrompt = PendingScan & {
    code: 'destination_mismatch' | 'consignment_split' | 'already_assigned';
    message: string;
    details?: Record<string, unknown>;
};

type Props = {
    loader: {
        id: number;
        name: string;
    };
    destinations: Destination[];
};

export default function Loading({ loader, destinations }: Props) {
    const [destinationId, setDestinationId] = useState('');
    const [manifestId, setManifestId] = useState('');
    const [barcode, setBarcode] = useState('');
    const [manifests, setManifests] = useState<Manifest[]>([]);
    const [loading, setLoading] = useState(false);
    const [scanning, setScanning] = useState(false);
    const [simulating, setSimulating] = useState(false);
    const [error, setError] = useState('');
    const [scanResult, setScanResult] = useState<ScanResult | null>(null);
    const [warningPrompt, setWarningPrompt] =
        useState<WarningPrompt | null>(null);

    useEffect(() => {
        setManifestId('');
        setManifests([]);
        setError('');
        setScanResult(null);
        setWarningPrompt(null);

        if (!destinationId) {
            return;
        }

        const controller = new AbortController();

        async function fetchManifests() {
            setLoading(true);

            try {
                const params = new URLSearchParams({
                    destination_id: destinationId,
                });

                const response = await fetch(
                    `/loading/manifests?${params.toString()}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error('Unable to load manifests.');
                }

                const json = await response.json();
                setManifests(json.data);
            } catch (fetchError) {
                if ((fetchError as Error).name !== 'AbortError') {
                    setError('Unable to load manifests.');
                }
            } finally {
                setLoading(false);
            }
        }

        fetchManifests();

        return () => controller.abort();
    }, [destinationId]);

    async function submitScan(scan: PendingScan) {
        if (!manifestId || !scan.barcode) {
            return;
        }

        setScanning(true);
        setError('');
        setScanResult(null);

        const xsrfToken = document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        try {
            const response = await fetch(
                `/loading/manifests/${manifestId}/scan`,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(xsrfToken
                            ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) }
                            : {}),
                    },
                    body: JSON.stringify({
                        barcode: scan.barcode,
                        client_event_id: scan.clientEventId,
                        occurred_at: scan.occurredAt,
                        acknowledged_warnings: scan.acknowledgedWarnings,
                    }),
                },
            );

            const json = await response.json();

            if (!response.ok) {
                const warningCodes = [
                    'destination_mismatch',
                    'consignment_split',
                    'already_assigned',
                ] as const;

                if (warningCodes.includes(json.error?.code)) {
                    setWarningPrompt({
                        ...scan,
                        code: json.error.code,
                        message:
                            json.error.message ??
                            'This scan needs confirmation.',
                        details: json.error.details,
                    });
                    return;
                }

                throw new Error(
                    json.error?.message ?? 'Unable to load this pallet.',
                );
            }

            setBarcode('');
            setWarningPrompt(null);
            setScanResult({
                barcode: scan.barcode,
                loader: json.data.loader?.name,
                scannedAt: json.data.loaded_at,
                progress: json.data.progress,
            });
        } catch (scanError) {
            setError((scanError as Error).message);
        } finally {
            setScanning(false);
        }
    }

    function scanBarcode(scanBarcode: string) {
        if (!scanBarcode) {
            return;
        }

        void submitScan({
            barcode: scanBarcode,
            clientEventId: crypto.randomUUID(),
            occurredAt: new Date().toISOString(),
            acknowledgedWarnings: [],
        });
    }

    async function simulateScan() {
        if (!manifestId) {
            return;
        }

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

            setBarcode(json.data.barcode);
            scanBarcode(json.data.barcode);
        } catch (simulationError) {
            setError((simulationError as Error).message);
        } finally {
            setSimulating(false);
        }
    }

    function confirmWarning() {
        if (!warningPrompt) {
            return;
        }

        void submitScan({
            ...warningPrompt,
            acknowledgedWarnings: [
                ...warningPrompt.acknowledgedWarnings,
                warningPrompt.code,
            ],
        });
    }

    return (
        <AppLayout>
            <Head title="Loading" />

            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Manifest loading</h1>
                    <p className="text-muted-foreground">
                        Select a destination and manifest to begin loading.
                    </p>
                </div>

                <div>
                    <label
                        htmlFor="destination"
                        className="block text-sm font-medium"
                    >
                        Destination
                    </label>

                    <select
                        id="destination"
                        className="mt-2 w-full rounded-md border p-3"
                        value={destinationId}
                        onChange={(event) =>
                            setDestinationId(event.target.value)
                        }
                    >
                        <option value="">Select a destination</option>

                        {destinations.map((destination) => (
                            <option key={destination.id} value={destination.id}>
                                {destination.code} — {destination.name}
                            </option>
                        ))}
                    </select>
                </div>

                {destinationId && (
                    <div>
                        <label
                            htmlFor="manifest"
                            className="block text-sm font-medium"
                        >
                            Manifest
                        </label>

                        {loading && (
                            <p className="mt-2 text-sm">Loading manifests…</p>
                        )}

                        {error && (
                            <p className="mt-2 text-sm text-red-600">{error}</p>
                        )}

                        {!loading && !error && (
                            <select
                                id="manifest"
                                className="mt-2 w-full rounded-md border p-3"
                                value={manifestId}
                                onChange={(event) =>
                                    setManifestId(event.target.value)
                                }
                            >
                                <option value="">Select a manifest</option>

                                {manifests.map((manifest) => (
                                    <option
                                        key={manifest.id}
                                        value={manifest.id}
                                        disabled={manifest.status !== 'open'}
                                    >
                                        {manifest.manifest_number} —{' '}
                                        {manifest.service_date} —{' '}
                                        {manifest.status === 'open'
                                            ? `${manifest.manifest_items_count} loaded`
                                            : 'Closed'}
                                    </option>
                                ))}
                            </select>
                        )}

                        {!loading && !error && manifests.length === 0 && (
                            <p className="mt-2 text-sm text-muted-foreground">
                                No open manifests found for this destination.
                            </p>
                        )}
                    </div>
                )}

                {manifestId && (
                    <div className="space-y-4 rounded-md border p-4">
                        <div>
                            <h2 className="font-medium">Scan pallet</h2>
                            <p className="text-sm text-muted-foreground">
                                Use a scanner, enter a barcode manually, or
                                simulate a seeded pallet.
                            </p>
                        </div>

                        <div className="rounded-md bg-muted p-3 text-sm">
                            <span className="font-medium">Loader:</span>{' '}
                            {loader.name}
                        </div>

                        <div className="flex gap-2">
                            <input
                                className="min-w-0 flex-1 rounded-md border p-3"
                                value={barcode}
                                onChange={(event) =>
                                    setBarcode(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        void scanBarcode(barcode.trim());
                                    }
                                }}
                                placeholder="Scan or enter pallet barcode"
                                autoFocus
                                disabled={scanning || simulating}
                            />

                            <button
                                type="button"
                                className="rounded-md bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
                                onClick={() => void scanBarcode(barcode.trim())}
                                disabled={
                                    !barcode.trim() || scanning || simulating
                                }
                            >
                                {scanning ? 'Loading…' : 'Load'}
                            </button>
                        </div>

                        <button
                            type="button"
                            className="rounded-md border px-4 py-2 text-sm disabled:opacity-50"
                            onClick={() => void simulateScan()}
                            disabled={scanning || simulating}
                        >
                            {simulating
                                ? 'Simulating scan…'
                                : 'Simulate seeded scan'}
                        </button>

                        {error && (
                            <p className="text-sm text-red-600">{error}</p>
                        )}

                        {scanResult && (
                            <div className="space-y-2 rounded-md bg-green-50 p-3 text-sm text-green-800">
                                <p>Loaded {scanResult.barcode} successfully.</p>

                                {scanResult.loader && scanResult.scannedAt && (
                                    <p>
                                        Scanned by {scanResult.loader} at{' '}
                                        {new Date(
                                            scanResult.scannedAt,
                                        ).toLocaleString()}
                                        .
                                    </p>
                                )}

                                {scanResult.progress && (
                                    <p className="font-medium">
                                        {scanResult.progress.loaded_count} of{' '}
                                        {scanResult.progress.total_count}{' '}
                                        destination pallets loaded.
                                    </p>
                                )}
                            </div>
                        )}
                    </div>
                )}

                {warningPrompt && (
                    <div
                        className="space-y-4 rounded-md border border-amber-300 bg-amber-50 p-4 text-amber-950"
                        role="alertdialog"
                        aria-labelledby="warning-title"
                    >
                        <div>
                            <h2 id="warning-title" className="font-semibold">
                                Confirm loading warning
                            </h2>
                            <p className="mt-1 text-sm">
                                {warningPrompt.message}
                            </p>

                            {warningPrompt.code === 'already_assigned' &&
                                warningPrompt.details && (
                                    <dl className="space-y-1 text-sm">
                                        <div>
                                            <dt className="inline font-medium">
                                                Previous manifest:{' '}
                                            </dt>
                                            <dd className="inline">
                                                {String(
                                                    warningPrompt.details
                                                        .previous_manifest_number,
                                                )}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="inline font-medium">
                                                Previous loader:{' '}
                                            </dt>
                                            <dd className="inline">
                                                {String(
                                                    warningPrompt.details
                                                        .previous_loader_name,
                                                )}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="inline font-medium">
                                                Previous scan:{' '}
                                            </dt>
                                            <dd className="inline">
                                                {new Date(
                                                    String(
                                                        warningPrompt.details
                                                            .previous_loaded_at,
                                                    ),
                                                ).toLocaleString()}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="inline font-medium">
                                                Move to:{' '}
                                            </dt>
                                            <dd className="inline">
                                                {String(
                                                    warningPrompt.details
                                                        .selected_manifest_number,
                                                )}
                                            </dd>
                                        </div>
                                    </dl>
                                )}
                        </div>

                        <div className="flex gap-2">
                            <button
                                type="button"
                                className="rounded-md bg-amber-700 px-4 py-2 text-sm text-white disabled:opacity-50"
                                onClick={confirmWarning}
                                disabled={scanning}
                            >
                                {scanning
                                    ? 'Confirming…'
                                    : warningPrompt.code ===
                                        'already_assigned'
                                      ? 'Override and load here'
                                      : 'Acknowledge and load'}
                            </button>
                            <button
                                type="button"
                                className="rounded-md border border-amber-700 px-4 py-2 text-sm"
                                onClick={() => setWarningPrompt(null)}
                                disabled={scanning}
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
