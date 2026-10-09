import { Minus, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    value: number;
    onChange: (delta: number) => void;
    disabled?: boolean;
    // A Tailwind background class for a colour dot (e.g. red / blue pallets).
    swatch?: string;
    compact?: boolean;
};

export default function Stepper({
    label,
    value,
    onChange,
    disabled = false,
    swatch,
    compact = false,
}: Props) {
    return (
        <div
            className={cn(
                'flex items-center gap-2 rounded-2xl border bg-card px-3',
                compact ? 'py-1.5' : 'py-2',
            )}
        >
            <span className="flex min-w-0 flex-1 items-center gap-2">
                {swatch && (
                    <span
                        className={cn('size-4 shrink-0 rounded-full', swatch)}
                    />
                )}
                <span className="truncate text-sm font-medium">{label}</span>
            </span>

            <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label={`Fewer ${label}`}
                className="size-11 rounded-full"
                disabled={disabled || value === 0}
                onClick={() => onChange(-1)}
            >
                <Minus className="size-5" />
            </Button>

            <span
                aria-live="polite"
                className="w-9 text-center text-xl font-bold tabular-nums"
            >
                {value}
            </span>

            <Button
                type="button"
                size="icon"
                aria-label={`More ${label}`}
                className="size-11 rounded-full"
                disabled={disabled}
                onClick={() => onChange(1)}
            >
                <Plus className="size-5" />
            </Button>
        </div>
    );
}
