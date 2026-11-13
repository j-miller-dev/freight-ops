import { Apple, TriangleAlert } from 'lucide-react';
import DgDiamond from '@/components/loading/dg-diamond';
import { cn } from '@/lib/utils';
import type { ManifestSummary, SegregationIssue } from '@/types/loading';

function describe(issue: SegregationIssue): string {
    const pair = `${issue.a_label} and ${issue.b_label}`;

    switch (issue.status) {
        case 'clash':
            return `${pair} are both on trailer ${issue.unit_names.join(' and ')}. They must not travel together.`;
        case 'unavoidable':
            return `${pair} must be kept apart, but there is no part of the trailer left for one of them.`;
        default:
            return `${pair} are both on this trailer. Tap A or B below to say where each one is.`;
    }
}

// Clashes and "no room to separate" are red; "nobody has said which unit is
// which yet" is amber, since it is fixed by labelling a unit.
export function ClashBanner({ summary }: { summary: ManifestSummary }) {
    const { issues } = summary.segregation;

    if (issues.length === 0) {
        return null;
    }

    return (
        <div className="mb-4 space-y-2">
            {issues.map((issue) => (
                <p
                    key={`${issue.a}-${issue.b}-${issue.status}`}
                    role="alert"
                    className={cn(
                        'flex items-center gap-3 rounded-2xl px-4 py-3 text-base font-semibold',
                        issue.status === 'undecided'
                            ? 'bg-amber-500/20 text-amber-800 dark:text-amber-200'
                            : 'bg-destructive text-white',
                    )}
                >
                    <TriangleAlert className="size-6 shrink-0" />
                    <span>
                        {describe(issue)}
                        {issue.reason && (
                            <span className="block text-sm font-normal opacity-90">
                                {issue.reason}
                            </span>
                        )}
                    </span>
                </p>
            ))}
        </div>
    );
}

export default function TrailerAlerts({
    summary,
    strip = false,
}: {
    summary: ManifestSummary;
    // A slim row with no card or title, hidden when there is nothing on board.
    strip?: boolean;
}) {
    const empty = summary.dg.length === 0 && summary.food_count === 0;

    if (strip && empty) {
        return null;
    }

    return (
        <div
            className={cn(
                strip ? 'px-1' : 'rounded-2xl border bg-card px-4 py-3',
            )}
        >
            {!strip && (
                <p className="text-xs text-muted-foreground">On board</p>
            )}

            {empty ? (
                <p className="text-lg font-semibold text-muted-foreground">
                    No DG or food
                </p>
            ) : (
                <div
                    className={cn(
                        'flex flex-wrap items-center gap-3',
                        !strip && 'mt-1',
                    )}
                >
                    {summary.dg.map((group) => (
                        <span
                            key={group.class}
                            className="flex items-center gap-1"
                            title={`${group.count} pallet(s) of class ${group.class}`}
                        >
                            <DgDiamond cls={group.class} size={34} />
                            <span className="text-sm font-bold tabular-nums">
                                ×{group.count}
                            </span>
                        </span>
                    ))}

                    {summary.food_count > 0 && (
                        <span
                            className="flex items-center gap-1 rounded-full bg-success/15 px-2.5 py-1 text-success"
                            title={`${summary.food_count} food pallet(s)`}
                        >
                            <Apple className="size-5" />
                            <span className="text-sm font-bold tabular-nums">
                                ×{summary.food_count}
                            </span>
                        </span>
                    )}
                </div>
            )}
        </div>
    );
}
