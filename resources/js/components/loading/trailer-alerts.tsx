import { Apple, TriangleAlert } from 'lucide-react';
import DgDiamond from '@/components/loading/dg-diamond';
import type { ManifestSummary } from '@/types/loading';

export function ClashBanner({ summary }: { summary: ManifestSummary }) {
    if (summary.food_conflicts.length === 0) {
        return null;
    }

    return (
        <p
            role="alert"
            className="mb-4 flex items-center gap-3 rounded-2xl bg-destructive px-4 py-3 text-base font-semibold text-white"
        >
            <TriangleAlert className="size-6 shrink-0" />
            Food on board with class {summary.food_conflicts.join(' and ')}.
            These must not travel together.
        </p>
    );
}

export default function TrailerAlerts({
    summary,
}: {
    summary: ManifestSummary;
}) {
    const empty = summary.dg.length === 0 && summary.food_count === 0;

    return (
        <div className="rounded-2xl border bg-card px-4 py-3">
            <p className="text-xs text-muted-foreground">On board</p>

            {empty ? (
                <p className="text-lg font-semibold text-muted-foreground">
                    No DG or food
                </p>
            ) : (
                <div className="mt-1 flex flex-wrap items-center gap-3">
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
