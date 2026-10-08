import {
    Apple,
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    Package,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import AlertError from '@/components/alert-error';
import DgDiamond from '@/components/loading/dg-diamond';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { getJson } from '@/lib/http';
import { cn } from '@/lib/utils';
import type {
    ConsignmentDetail,
    ConsignmentRow,
    ConsignmentState,
    Piece,
    PieceEvent,
    PieceState,
} from '@/types/loading';

type Props = {
    manifestId: string;
    manifestNumber: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

type Page = {
    data: ConsignmentRow[];
    meta: { current_page: number; last_page: number; total: number };
};

const FILTERS = [
    { value: 'all', label: 'All' },
    { value: 'ready', label: 'Ready in bay' },
    { value: 'not_ready', label: 'Not in bay' },
    { value: 'complete', label: 'Complete' },
] as const;

// Green means "everything still to load is sitting in the bay"; amber means
// something is missing from it, so there is no point searching the bay alone.
const STATE: Record<
    ConsignmentState,
    { label: (row: ConsignmentRow) => string; border: string; chip: string }
> = {
    ready: {
        label: (row) => `Ready in ${row.bay_code ?? 'bay'}`,
        border: 'border-l-success',
        chip: 'bg-success text-success-foreground',
    },
    not_ready: {
        label: (row) => `${row.not_in_bay} not in ${row.bay_code ?? 'bay'}`,
        border: 'border-l-warning',
        chip: 'bg-warning text-warning-foreground',
    },
    complete: {
        label: () => 'All loaded',
        border: 'border-l-primary',
        chip: 'bg-primary text-primary-foreground',
    },
    elsewhere: {
        label: () => 'Rest on other trailers',
        border: 'border-l-muted-foreground/50',
        chip: 'bg-muted text-muted-foreground',
    },
};

const PIECE_TONE: Record<PieceState, string> = {
    this_trailer: 'bg-primary text-primary-foreground',
    other_trailer: 'bg-muted text-foreground',
    in_bay: 'bg-success text-success-foreground',
    elsewhere: 'bg-warning text-warning-foreground',
    not_scanned_in: 'bg-destructive text-white',
};

function time(iso: string): string {
    return new Date(iso).toLocaleTimeString([], {
        hour: 'numeric',
        minute: '2-digit',
    });
}

function eventLabel(event: PieceEvent): string {
    switch (event.type) {
        case 'received':
            return `Scanned into depot${event.location_code ? ` (${event.location_code})` : ''}`;
        case 'staged':
            return `Placed in ${event.location_code ?? 'a location'}`;
        case 'loaded':
            return `Loaded on ${event.manifest_number ?? 'a trailer'}`;
        default:
            return event.type.replaceAll('_', ' ');
    }
}

function Flags({ dg, food }: { dg: string[]; food: boolean }) {
    return (
        <>
            {dg.map((cls) => (
                <DgDiamond key={cls} cls={cls} size={26} />
            ))}
            {food && <Apple className="size-5 text-success" />}
        </>
    );
}

function PieceRow({ piece }: { piece: Piece }) {
    const [open, setOpen] = useState(false);

    return (
        <li className="rounded-xl border bg-card">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                className="flex w-full items-center gap-3 px-4 py-3 text-left"
            >
                <span className="w-9 text-lg font-bold tabular-nums">
                    {piece.piece_number}
                </span>

                <span className="min-w-0 flex-1">
                    <span className="block truncate font-mono text-sm">
                        {piece.barcode}
                    </span>
                    {piece.dg_class && (
                        <span className="block truncate text-xs text-muted-foreground">
                            {piece.un_number} {piece.proper_shipping_name}
                        </span>
                    )}
                </span>

                <Flags
                    dg={piece.dg_class ? [piece.dg_class] : []}
                    food={piece.is_food}
                />

                <span
                    className={cn(
                        'rounded-full px-3 py-1 text-xs font-semibold',
                        PIECE_TONE[piece.where.state],
                    )}
                >
                    {piece.where.label}
                </span>
            </button>

            {open && (
                <ol className="space-y-2 border-t px-4 py-3 text-sm">
                    {piece.events.length === 0 && (
                        <li className="text-muted-foreground">
                            No scans recorded yet.
                        </li>
                    )}
                    {piece.events.map((event, index) => (
                        <li
                            key={`${event.type}-${index}`}
                            className="flex items-center gap-2"
                        >
                            <CircleCheck className="size-4 shrink-0 text-success" />
                            <span className="flex-1">{eventLabel(event)}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {time(event.occurred_at)} · {event.actor}
                            </span>
                        </li>
                    ))}
                </ol>
            )}
        </li>
    );
}

export default function ConsignmentSheet({
    manifestId,
    manifestNumber,
    open,
    onOpenChange,
}: Props) {
    const [filter, setFilter] = useState<string>('all');
    const [page, setPage] = useState(1);
    const [list, setList] = useState<Page | null>(null);
    const [selected, setSelected] = useState<string | null>(null);
    const [detail, setDetail] = useState<ConsignmentDetail | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!open || selected) {
            return;
        }

        const controller = new AbortController();

        async function load() {
            setLoading(true);
            setError('');

            try {
                const params = new URLSearchParams({
                    filter,
                    page: String(page),
                });

                setList(
                    await getJson<Page>(
                        `/loading/manifests/${manifestId}/consignments?${params}`,
                        controller.signal,
                    ),
                );
            } catch (loadError) {
                if ((loadError as Error).name !== 'AbortError') {
                    setError('Unable to load consignments.');
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }

        void load();

        return () => controller.abort();
    }, [open, selected, filter, page, manifestId]);

    useEffect(() => {
        if (!open || !selected) {
            return;
        }

        const controller = new AbortController();

        async function load() {
            setLoading(true);
            setError('');

            try {
                const json = await getJson<{ data: ConsignmentDetail }>(
                    `/loading/manifests/${manifestId}/consignments/${selected}`,
                    controller.signal,
                );
                setDetail(json.data);
            } catch (loadError) {
                if ((loadError as Error).name !== 'AbortError') {
                    setError('Unable to load this consignment.');
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }

        void load();

        return () => controller.abort();
    }, [open, selected, manifestId]);

    const lastPage = list?.meta.last_page ?? 1;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full gap-0 sm:max-w-2xl">
                <SheetHeader className="border-b pr-12">
                    <SheetTitle className="text-xl">
                        Consignments on {manifestNumber}
                    </SheetTitle>
                    <SheetDescription>
                        {selected
                            ? 'Tap an item to see where it has been.'
                            : 'Tap a consignment to see each item.'}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto p-4">
                    {error && <AlertError errors={[error]} />}

                    {selected ? (
                        <div className="space-y-3">
                            <Button
                                variant="outline"
                                size="touch"
                                onClick={() => {
                                    setSelected(null);
                                    setDetail(null);
                                }}
                            >
                                <ChevronLeft /> Back to list
                            </Button>

                            {detail && (
                                <>
                                    <div>
                                        <p className="text-2xl font-bold tabular-nums">
                                            {detail.connote_number}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {detail.sender_name} →{' '}
                                            {detail.receiver_name} ·{' '}
                                            {detail.item_count} items
                                        </p>
                                    </div>

                                    <ul className="space-y-2">
                                        {detail.pieces.map((piece) => (
                                            <PieceRow
                                                key={piece.id}
                                                piece={piece}
                                            />
                                        ))}
                                    </ul>
                                </>
                            )}
                        </div>
                    ) : (
                        <>
                            <div className="mb-4 flex flex-wrap gap-2">
                                {FILTERS.map((option) => (
                                    <Button
                                        key={option.value}
                                        variant={
                                            filter === option.value
                                                ? 'default'
                                                : 'outline'
                                        }
                                        aria-pressed={filter === option.value}
                                        className="h-11 rounded-full"
                                        onClick={() => {
                                            setFilter(option.value);
                                            setPage(1);
                                        }}
                                    >
                                        {option.label}
                                    </Button>
                                ))}
                            </div>

                            {!loading && list?.data.length === 0 && (
                                <p className="py-10 text-center text-muted-foreground">
                                    No consignments match.
                                </p>
                            )}

                            <ul className="space-y-3">
                                {list?.data.map((row) => (
                                    <li key={row.id}>
                                        <button
                                            type="button"
                                            onClick={() => setSelected(row.id)}
                                            className={cn(
                                                'flex w-full items-center gap-3 rounded-2xl border border-l-8 bg-card px-4 py-3 text-left shadow-xs transition active:scale-[0.99]',
                                                STATE[row.state].border,
                                            )}
                                        >
                                            <Package className="size-6 shrink-0 text-muted-foreground" />

                                            <span className="min-w-0 flex-1">
                                                <span className="flex items-center gap-2">
                                                    <span className="text-lg font-bold tabular-nums">
                                                        {row.connote_number}
                                                    </span>
                                                    <Flags
                                                        dg={row.dg_classes}
                                                        food={row.has_food}
                                                    />
                                                </span>
                                                <span className="block truncate text-sm text-muted-foreground">
                                                    {row.sender_name}
                                                    {row.service_code &&
                                                        ` · ${row.service_code}`}
                                                </span>
                                                <span className="block text-xs text-muted-foreground tabular-nums">
                                                    {[
                                                        row.on_other_trailers >
                                                            0 &&
                                                            `${row.on_other_trailers} on other trailers`,
                                                        row.in_bay > 0 &&
                                                            `${row.in_bay} in ${row.bay_code ?? 'bay'}`,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </span>
                                            </span>

                                            <span className="text-right">
                                                <span className="block text-2xl font-bold tabular-nums">
                                                    {row.on_trailer}
                                                    <span className="text-base font-normal text-muted-foreground">
                                                        /{row.item_count}
                                                    </span>
                                                </span>
                                                <span
                                                    className={cn(
                                                        'mt-1 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                                        STATE[row.state].chip,
                                                    )}
                                                >
                                                    {STATE[row.state].label(
                                                        row,
                                                    )}
                                                </span>
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </div>

                {!selected && lastPage > 1 && (
                    <div className="flex items-center justify-between gap-4 border-t p-4">
                        <Button
                            variant="warning"
                            size="touch"
                            onClick={() => setPage(page - 1)}
                            disabled={page <= 1}
                        >
                            <ChevronLeft /> Prev
                        </Button>
                        <p className="text-muted-foreground tabular-nums">
                            {page} of {lastPage}
                        </p>
                        <Button
                            variant="warning"
                            size="touch"
                            onClick={() => setPage(page + 1)}
                            disabled={page >= lastPage}
                        >
                            Next <ChevronRight />
                        </Button>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
