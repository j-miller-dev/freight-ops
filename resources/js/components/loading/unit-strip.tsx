import { Pencil } from 'lucide-react';
import CategoryIcon from '@/components/loading/category-icon';
import { cn } from '@/lib/utils';
import type { Segregation, TrailerUnitState } from '@/types/loading';

type Props = {
    segregation: Segregation;
    disabled?: boolean;
    onSelect: (unit: TrailerUnitState) => void;
};

// A, B (and C) of the trailer, each showing what is on it. Tap one to say what
// it is carrying, so the whole crew can see which unit is which.
export default function UnitStrip({ segregation, disabled, onSelect }: Props) {
    // A single-unit trailer has no A/B to tell apart.
    if (segregation.units.length < 2) {
        return null;
    }

    return (
        <section
            aria-label="What is on each part of the trailer"
            className="mb-4 grid gap-2"
            style={{
                gridTemplateColumns: `repeat(${segregation.units.length}, minmax(0, 1fr))`,
            }}
        >
            {segregation.units.map((unit) => {
                const clashing = segregation.issues.some(
                    (issue) =>
                        issue.status === 'clash' &&
                        issue.units.includes(unit.unit),
                );

                return (
                    <button
                        key={unit.unit}
                        type="button"
                        disabled={disabled}
                        onClick={() => onSelect(unit)}
                        aria-label={`Trailer ${unit.name}: ${
                            unit.categories.length === 0
                                ? 'nothing labelled'
                                : unit.categories.map((c) => c.label).join(', ')
                        }. Tap to change.`}
                        className={cn(
                            'flex min-h-20 flex-col gap-2 rounded-2xl border bg-card p-3 text-left transition active:scale-[0.98] disabled:opacity-60',
                            clashing
                                ? 'border-destructive bg-destructive/10 ring-2 ring-destructive'
                                : 'hover:border-primary',
                        )}
                    >
                        <span className="flex items-center justify-between">
                            <span
                                className={cn(
                                    'flex size-8 items-center justify-center rounded-lg text-sm font-bold',
                                    clashing
                                        ? 'bg-destructive text-white'
                                        : 'bg-primary text-primary-foreground',
                                )}
                            >
                                {unit.name}
                            </span>
                            <Pencil className="size-4 text-muted-foreground" />
                        </span>

                        {unit.categories.length === 0 ? (
                            <span className="text-sm text-muted-foreground">
                                Tap to label
                            </span>
                        ) : (
                            <span className="flex flex-wrap items-center gap-2">
                                {unit.categories.map((category) => (
                                    <span
                                        key={category.key}
                                        className="flex items-center gap-1"
                                        title={category.label}
                                    >
                                        <CategoryIcon
                                            category={category.key}
                                            size={28}
                                        />
                                        <span className="text-xs font-medium">
                                            {category.label}
                                        </span>
                                    </span>
                                ))}
                            </span>
                        )}
                    </button>
                );
            })}
        </section>
    );
}
