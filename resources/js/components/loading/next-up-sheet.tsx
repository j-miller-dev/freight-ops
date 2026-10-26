import { Apple, RefreshCw } from 'lucide-react';
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
import type { NextUpList, NextUpRow, PriorityTier } from '@/types/loading';

type Props = {
    manifestId: string;
    manifestNumber: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

// P1 is the most urgent. Red and amber follow the colours used for trailer
// departure and "not in bay" elsewhere on the page.
const TIER: Record<PriorityTier, { chip: string; border: string }> = {
    P1: { chip: 'bg-destructive text-white', border: 'border-l-destructive' },
    P2: { chip: 'bg-orange-500 text-white', border: 'border-l-orange-500' },
    P3: {
        chip: 'bg-warning text-warning-foreground',
        border: 'border-l-warning',
    },
    P4: { chip: 'bg-muted text-foreground', border: 'border-l-border' },
};

function readyLabel({ ready, item_count, on_trailer }: NextUpRow): string {
    const parts = [`${ready} of ${item_count} ready`];

    if (on_trailer > 0) {
        parts.push(`${on_trailer} on this trailer`);
    }

    return parts.join(' · ');
}

function Row({ row }: { row: NextUpRow }) {
    return (
        <li
            className={cn(
                'flex items-start gap-3 rounded-2xl border border-l-8 bg-card px-4 py-3 shadow-xs',
                TIER[row.tier].border,
            )}
        >
            <span
                className={cn(
                    'mt-0.5 rounded-lg px-2.5 py-1 text-sm font-black',
                    TIER[row.tier].chip,
                )}
                title={`Urgency ${row.score}`}
            >
                {row.tier}
            </span>

            <span className="min-w-0 flex-1">
                <span className="flex flex-wrap items-center gap-2">
                    <span className="text-lg font-bold">
                        {row.connote_number}
                    </span>
                    {row.dg_classes.map((cls) => (
                        <DgDiamond key={cls} cls={cls} size={26} />
                    ))}
                    {row.has_food && <Apple className="size-5 text-success" />}
                </span>

                <span className="block truncate text-sm text-muted-foreground">
                    {[row.sender_name, row.service_code]
                        .filter(Boolean)
                        .join(' · ')}
                </span>

                {row.reasons.length > 0 && (
                    <ul className="mt-1 space-y-0.5 text-sm">
                        {row.reasons.map((reason) => (
                            <li key={reason}>{reason}</li>
                        ))}
                    </ul>
                )}
            </span>

            <span className="shrink-0 text-right text-sm font-medium tabular-nums">
                {readyLabel(row)}
            </span>
        </li>
    );
}

export default function NextUpSheet({
    manifestId,
    manifestNumber,
    open,
    onOpenChange,
}: Props) {
    const [list, setList] = useState<NextUpList | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    // Bumped by Refresh to look again without closing the sheet.
    const [reload, setReload] = useState(0);

    // Freight arrives and trailers fill while this is open, so look again
    // every time it is opened.
    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();

        async function load() {
            setLoading(true);
            setError('');

            try {
                setList(
                    await getJson<NextUpList>(
                        `/loading/manifests/${manifestId}/next-up`,
                        controller.signal,
                    ),
                );
            } catch (loadError) {
                if ((loadError as Error).name !== 'AbortError') {
                    setError('Unable to load what is next.');
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }

        void load();

        return () => controller.abort();
    }, [open, manifestId, reload]);

    const meta = list?.meta;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full gap-0 sm:max-w-2xl">
                <SheetHeader className="border-b pr-12">
                    <SheetTitle className="text-xl">
                        Next up for {manifestNumber}
                    </SheetTitle>
                    <SheetDescription>
                        {meta?.bay_code
                            ? `Ready in ${meta.bay_code}, most urgent first.`
                            : 'Ready in the bay, most urgent first.'}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto p-4">
                    {error && <AlertError errors={[error]} />}

                    {list && list.data.length === 0 && !loading && (
                        <p className="py-8 text-center text-lg text-muted-foreground">
                            {meta?.bay_code
                                ? 'Nothing in the bay is ready for this trailer.'
                                : 'This trailer has no bay set up yet.'}
                        </p>
                    )}

                    <ol className="space-y-3">
                        {list?.data.map((row) => (
                            <Row key={row.id} row={row} />
                        ))}
                    </ol>
                </div>

                <div className="flex items-center gap-3 border-t px-4 py-3">
                    <p className="min-w-0 flex-1 text-sm text-muted-foreground">
                        {meta && meta.total > meta.shown
                            ? `Showing ${meta.shown} of ${meta.total}. `
                            : ''}
                        {meta && meta.held_count > 0
                            ? `${meta.held_count} held and not listed.`
                            : ''}
                    </p>
                    <Button
                        variant="outline"
                        size="touch"
                        onClick={() => setReload((count) => count + 1)}
                        disabled={loading}
                    >
                        <RefreshCw className={cn(loading && 'animate-spin')} />
                        Refresh
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
