import { cn } from '@/lib/utils';

type Props = {
    itemCount: number;
    inBay: number;
    held: number;
    loaded?: number;
    bayCode: string;
    // The pallet in hand is not counted yet; say what it will make.
    placing?: boolean;
};

// The scanned consignment's pill: how much of this order is already in the
// bay, so the runner can see how far along it is and what is still out there.
export default function ConsignmentProgress({
    itemCount,
    inBay,
    held,
    loaded = 0,
    bayCode,
    placing = false,
}: Props) {
    const outThere = Math.max(0, itemCount - inBay - held - loaded);
    const share = itemCount > 0 ? (inBay + held) / itemCount : 0;
    const complete = outThere === 0;

    return (
        <div
            className={cn(
                'space-y-2 rounded-2xl border p-4 text-left',
                complete
                    ? 'border-success bg-success/10'
                    : 'border-border bg-muted/40',
            )}
        >
            <p className="flex items-baseline justify-between gap-3">
                <span className="text-2xl font-black tabular-nums">
                    {inBay} of {itemCount}
                    <span className="text-base font-semibold text-muted-foreground">
                        {' '}
                        in {bayCode}
                    </span>
                </span>
                {complete && (
                    <span className="text-sm font-semibold text-success">
                        All placed
                    </span>
                )}
            </p>

            <div
                className="h-3 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={itemCount}
                aria-valuenow={inBay + held}
            >
                <div
                    className={cn(
                        'h-full rounded-full transition-all',
                        complete ? 'bg-success' : 'bg-primary',
                    )}
                    style={{ width: `${Math.round(share * 100)}%` }}
                />
            </div>

            <p className="text-sm text-muted-foreground">
                {[
                    outThere > 0 ? `${outThere} still out there` : null,
                    held > 0 ? `${held} held` : null,
                    loaded > 0 ? `${loaded} already loaded` : null,
                    placing && !complete ? `this one makes ${inBay + 1}` : null,
                ]
                    .filter(Boolean)
                    .join(' · ') || 'Nothing left to place'}
            </p>
        </div>
    );
}
