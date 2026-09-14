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
    connote_number?: string;
    piece_number?: number;
};

type Props = {
    destinations: Destination[];
};

export default function Loading({ destinations }: Props) {
    const [destinationId, setDestinationId] = useState('');
    const [manifestId, setManifestId] = useState('');
    const [barcode, setBarcode] = useState('');
    const [manifests, setManifests] = useState<Manifest[]>([]);
    const [loading, setLoading] = useState(false);
    const [scanning, setScanning] = useState(false);
    const [simulating, setSimulating] = useState(false);
    const [error, setError] = useState('');
    const [scanResult, setScanResult] = useState<ScanResult | null>(null);

    useEffect(() => {
        setManifestId('');
        setManifests([]);
        setError('');
        setScanResult(null);

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

    async function scanBarcode(scanBarcode: string) {
        if (!manifestId || !scanBarcode) {
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
                        barcode: scanBarcode,
                        client_event_id: crypto.randomUUID(),
                        occurred_at: new Date().toISOString(),
                    }),
                },
            );

            const json = await response.json();

            if (!response.ok) {
                throw new Error(
                    json.error?.message ?? 'Unable to load this pallet.',
                );
            }

            setScanResult({ barcode: scanBarcode });
        } catch (scanError) {
            setError((scanError as Error).message);
        } finally {
            setScanning(false);
        }
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
            await scanBarcode(json.data.barcode);
        } catch (simulationError) {
            setError((simulationError as Error).message);
        } finally {
            setSimulating(false);
        }
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
                                    >
                                        {manifest.manifest_number} —{' '}
                                        {manifest.service_date} —{' '}
                                        {manifest.manifest_items_count} loaded
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
                            <div className="rounded-md bg-green-50 p-3 text-sm text-green-800">
                                Loaded {scanResult.barcode} successfully.
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
