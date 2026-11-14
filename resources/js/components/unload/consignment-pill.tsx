import { cn } from '@/lib/utils';
import type { ConsignmentPillData } from '@/types/unload';

function tone(share: number): { fill: string; text: string; border: string } {
    if (share >= 0.67) {
        return {
            fill: 'bg-success/25',
            text: 'text-success',
            border: 'border-success/60',
        };
    }

    if (share >= 0.34) {
        return {
            fill: 'bg-amber-500/25',
            text: 'text-amber-700 dark:text-amber-300',
            border: 'border-amber-500/60',
        };
    }

    return {
        fill: 'bg-muted-foreground/15',
        text: 'text-muted-foreground',
        border: 'border-border',
    };
}

// One consignment as a pill: how many of its pieces are in the bay, with the
// pill filling up as the order comes together.
export default function ConsignmentPill({
    pill,
    bayCode,
}: {
    pill: ConsignmentPillData;
    bayCode: string;
}) {
    const colours = tone(pill.placed_share);

    return (
        <li
            className={cn(
                'relative overflow-hidden rounded-2xl border bg-card',
                colours.border,
            )}
            aria-label={`${pill.sender_name ?? ''} ${pill.connote_number}: ${pill.in_bay} of ${pill.item_count} in ${bayCode}, ${pill.out_there} still to place`}
        >
            <span
                aria-hidden
                className={cn('absolute inset-y-0 left-0', colours.fill)}
                style={{ width: `${Math.round(pill.placed_share * 100)}%` }}
            />
            <span className="relative flex items-center justify-between gap-3 px-4 py-2">
                <span className="min-w-0">
                    <span className="block truncate text-base font-bold">
                        {pill.sender_name ?? pill.connote_number}
                    </span>
                    {pill.sender_name && (
                        <span className="block text-xs text-muted-foreground">
                            {pill.connote_number}
                        </span>
                    )}
                </span>
                <span
                    className={cn(
                        'shrink-0 text-base font-semibold tabular-nums',
                        colours.text,
                    )}
                >
                    {pill.in_bay} of {pill.item_count}
                </span>
            </span>
        </li>
    );
}
