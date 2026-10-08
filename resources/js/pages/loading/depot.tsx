import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Lock, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import AlertError from '@/components/alert-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Destination = {
    id: string;
    code: string;
    name: string;
};

type Manifest = {
    id: string;
    manifest_number: string;
    service_date: string;
    departs_at: string | null;
    status: string;
    manifest_items_count: number;
};

type Props = {
    destination: Destination;
};

const PAGE_SIZE = 5;

// service_date arrives as an ISO timestamp; only the calendar day matters, and
// parsing it as a full instant would shift it across timezones.
function formatServiceDate(value: string): string {
    return new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString(
        undefined,
        { weekday: 'short', day: 'numeric', month: 'short' },
    );
}

export default function LoadingDepot({ destination }: Props) {
    const [manifests, setManifests] = useState<Manifest[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [page, setPage] = useState(0);
    const [includeYesterday, setIncludeYesterday] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        const controller = new AbortController();

        async function fetchManifests() {
            setLoading(true);
            setError('');

            try {
                const params = new URLSearchParams({
                    destination_id: destination.id,
                    ...(includeYesterday ? { include_yesterday: '1' } : {}),
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
                    setError(
                        'Unable to load manifests. Check your connection.',
                    );
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }

        void fetchManifests();

        return () => controller.abort();
    }, [destination.id, includeYesterday, refreshKey]);

    const pageCount = Math.max(1, Math.ceil(manifests.length / PAGE_SIZE));
    const currentPage = Math.min(page, pageCount - 1);
    const visible = manifests.slice(
        currentPage * PAGE_SIZE,
        (currentPage + 1) * PAGE_SIZE,
    );

    return (
        <>
            <Head title={`Manifests - ${destination.code}`} />

            <PageHeader
                title={`Manifests · ${destination.code}`}
                subtitle={destination.name}
                backHref="/loading"
                actions={
                    <Button
                        variant="outline"
                        size="touch"
                        onClick={() => setRefreshKey((key) => key + 1)}
                        disabled={loading}
                    >
                        <RefreshCw className={cn(loading && 'animate-spin')} />
                        <span className="hidden sm:inline">Refresh</span>
                    </Button>
                }
            />

            <div className="mb-4">
                <Button
                    variant={includeYesterday ? 'default' : 'outline'}
                    aria-pressed={includeYesterday}
                    className="h-11 rounded-full"
                    onClick={() => {
                        setIncludeYesterday((value) => !value);
                        setPage(0);
                    }}
                >
                    Include yesterday
                </Button>
            </div>

            {error && <AlertError errors={[error]} />}

            {!error && !loading && manifests.length === 0 && (
                <p className="py-12 text-center text-muted-foreground">
                    No manifests for {destination.code} today.
                </p>
            )}

            <ul className="space-y-3">
                {visible.map((manifest) => {
                    const open = manifest.status === 'open';
                    const content = (
                        <>
                            <div className="min-w-0 flex-1">
                                <p className="text-2xl font-bold tabular-nums">
                                    {manifest.manifest_number}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {formatServiceDate(manifest.service_date)}
                                    {manifest.departs_at && open && (
                                        <span className="font-semibold text-foreground">
                                            {' · '}Departs{' '}
                                            {new Date(
                                                manifest.departs_at,
                                            ).toLocaleTimeString([], {
                                                hour: 'numeric',
                                                minute: '2-digit',
                                            })}
                                        </span>
                                    )}
                                </p>
                            </div>

                            {open ? (
                                <div className="text-right">
                                    <p className="text-2xl font-bold tabular-nums">
                                        {manifest.manifest_items_count}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        loaded
                                    </p>
                                </div>
                            ) : (
                                <span className="flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
                                    <Lock className="size-4" /> Closed
                                </span>
                            )}

                            {open && (
                                <ChevronRight className="size-6 text-muted-foreground" />
                            )}
                        </>
                    );

                    const rowClass =
                        'flex items-center gap-4 rounded-2xl border border-l-8 bg-card px-5 py-4 shadow-xs';

                    return (
                        <li key={manifest.id}>
                            {open ? (
                                <Link
                                    href={`/loading/manifests/${manifest.id}`}
                                    className={cn(
                                        rowClass,
                                        'border-l-success transition hover:shadow-md active:scale-[0.99]',
                                    )}
                                >
                                    {content}
                                </Link>
                            ) : (
                                <div
                                    aria-disabled="true"
                                    className={cn(
                                        rowClass,
                                        'border-l-muted-foreground/40 opacity-60',
                                    )}
                                >
                                    {content}
                                </div>
                            )}
                        </li>
                    );
                })}
            </ul>

            {pageCount > 1 && (
                <div className="mt-6 flex items-center justify-between gap-4">
                    <Button
                        variant="warning"
                        size="touch"
                        onClick={() => setPage(currentPage - 1)}
                        disabled={currentPage === 0}
                        aria-label="Previous page"
                    >
                        <ChevronLeft /> Prev
                    </Button>

                    <p className="text-muted-foreground tabular-nums">
                        {currentPage + 1} of {pageCount}
                    </p>

                    <Button
                        variant="warning"
                        size="touch"
                        onClick={() => setPage(currentPage + 1)}
                        disabled={currentPage >= pageCount - 1}
                        aria-label="Next page"
                    >
                        Next <ChevronRight />
                    </Button>
                </div>
            )}
        </>
    );
}
