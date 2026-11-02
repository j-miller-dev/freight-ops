import { Check, Clock, PackageSearch, TriangleAlert } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { getJson, postJson } from '@/lib/http';
import { cn } from '@/lib/utils';
import type { HoldingLanePallet } from '@/types/unload';

type Props = {
    laneId: string | null;
    laneCode: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    // Called after a release, so the lane strip can refresh.
    onReleased: () => void;
};

const STATUS_CHIP: Record<HoldingLanePallet['status'], string> = {
    overdue: 'bg-destructive text-white',
    opportunity: 'bg-warning text-warning-foreground',
    settled: 'bg-muted text-muted-foreground',
};

const STATUS_ICON = {
    overdue: TriangleAlert,
    opportunity: Clock,
    settled: PackageSearch,
} as const;

export default function HoldingLaneDialog({
    laneId,
    laneCode,
    open,
    onOpenChange,
    onReleased,
}: Props) {
    const [pallets, setPallets] = useState<HoldingLanePallet[]>([]);
    const [loading, setLoading] = useState(false);
    const [releasingId, setReleasingId] = useState<string | null>(null);

    useEffect(() => {
        if (!open || !laneId) {
            return;
        }

        const controller = new AbortController();

        async function load() {
            setLoading(true);

            try {
                const json = await getJson<{ data: HoldingLanePallet[] }>(
                    `/unload/holding-lanes/${laneId}`,
                    controller.signal,
                );
                setPallets(json.data);
            } catch (loadError) {
                if ((loadError as Error).name !== 'AbortError') {
                    toast.error('Could not load this lane. Try again.');
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }

        void load();

        return () => controller.abort();
    }, [open, laneId]);

    async function release(pallet: HoldingLanePallet) {
        setReleasingId(pallet.id);

        try {
            await postJson(`/unload/pallets/${pallet.id}/release`);
            setPallets((current) =>
                current.map((row) =>
                    row.id === pallet.id ? { ...row, released: true } : row,
                ),
            );
            toast.success(
                `${pallet.barcode} released — next scan sends it to its real bay.`,
            );
            onReleased();
        } catch (error) {
            toast.error((error as Error).message);
        } finally {
            setReleasingId(null);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="text-xl">
                        Lane {laneCode}
                    </DialogTitle>
                    <DialogDescription>
                        Release a pallet to send it to its real bay next time
                        it's scanned, instead of back into holding.
                    </DialogDescription>
                </DialogHeader>

                {loading && (
                    <div className="flex justify-center py-6">
                        <Spinner />
                    </div>
                )}

                {!loading && pallets.length === 0 && (
                    <p className="py-4 text-center text-sm text-muted-foreground">
                        Nothing in this lane anymore.
                    </p>
                )}

                <div className="space-y-2">
                    {pallets.map((pallet) => {
                        const Icon = STATUS_ICON[pallet.status];

                        return (
                            <div
                                key={pallet.id}
                                className="flex items-center gap-3 rounded-xl border p-3"
                            >
                                <div
                                    className={cn(
                                        'flex size-9 shrink-0 items-center justify-center rounded-full',
                                        STATUS_CHIP[pallet.status],
                                    )}
                                >
                                    <Icon className="size-4" />
                                </div>

                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">
                                        {pallet.connote_number ??
                                            pallet.barcode}
                                        {pallet.sender_name && (
                                            <span className="font-normal text-muted-foreground">
                                                {' '}
                                                · {pallet.sender_name}
                                            </span>
                                        )}
                                    </p>
                                    {pallet.reasons[0] && (
                                        <p className="truncate text-sm text-muted-foreground">
                                            {pallet.reasons[0]}
                                        </p>
                                    )}
                                </div>

                                {pallet.released ? (
                                    <span className="flex shrink-0 items-center gap-1 text-sm font-medium text-success">
                                        <Check className="size-4" /> Released
                                    </span>
                                ) : (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="shrink-0"
                                        disabled={releasingId === pallet.id}
                                        onClick={() => void release(pallet)}
                                    >
                                        {releasingId === pallet.id && (
                                            <Spinner />
                                        )}
                                        Release
                                    </Button>
                                )}
                            </div>
                        );
                    })}
                </div>
            </DialogContent>
        </Dialog>
    );
}
