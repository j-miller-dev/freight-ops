import { Clock, PackageSearch, TriangleAlert } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { HoldingLane } from '@/types/unload';

const STATUS_STYLE: Record<
    HoldingLane['status'],
    { chip: string; icon: typeof TriangleAlert }
> = {
    overdue: { chip: 'bg-destructive text-white', icon: TriangleAlert },
    opportunity: {
        chip: 'bg-warning text-warning-foreground',
        icon: Clock,
    },
    settled: { chip: 'bg-muted text-muted-foreground', icon: PackageSearch },
};

type Props = {
    lanes: HoldingLane[];
    onSelect: (lane: HoldingLane) => void;
};

// What a runner needs to know about held freight without going to check each
// lane themselves: a lane only appears once it has freight in it, with the
// most urgent reason shown first. Tapping a lane opens its pallets.
export default function HoldingLanesStrip({ lanes, onSelect }: Props) {
    if (lanes.length === 0) {
        return null;
    }

    return (
        <div className="mb-4 flex gap-2 overflow-x-auto pb-1">
            {lanes.map((lane) => {
                const { chip, icon: Icon } = STATUS_STYLE[lane.status];

                return (
                    <button
                        key={lane.id}
                        type="button"
                        onClick={() => onSelect(lane)}
                        className={cn(
                            'flex shrink-0 items-center gap-2 rounded-xl px-3 py-2 text-left transition active:scale-[0.98]',
                            chip,
                        )}
                    >
                        <Icon className="size-4 shrink-0" />
                        <div className="text-sm leading-tight">
                            <p className="font-semibold">
                                {lane.code}
                                <span className="font-normal opacity-80">
                                    {' '}
                                    · {lane.pallet_count}{' '}
                                    {lane.pallet_count === 1
                                        ? 'pallet'
                                        : 'pallets'}
                                </span>
                            </p>
                            {lane.reasons[0] && (
                                <p className="opacity-90">{lane.reasons[0]}</p>
                            )}
                        </div>
                    </button>
                );
            })}
        </div>
    );
}
