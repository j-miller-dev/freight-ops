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

type Props = {
    destinations: Destination[];
};

export default function Loading({ destinations }: Props) {
    const [destinationId, setDestinationId] = useState('');
    const [manifestId, setManifestId] = useState('');
    const [manifests, setManifests] = useState<Manifest[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        setManifestId('');
        setManifests([]);
        setError('');

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
                    <div className="rounded-md border p-4">
                        Manifest selected. The barcode scanner can be added here
                        next.
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
