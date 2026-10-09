import { cn } from '@/lib/utils';
import type { TrailerTypeOption } from '@/types/loading';

type Props = {
    options: TrailerTypeOption[];
    value: string;
    onChange: (value: string) => void;
};

export default function TrailerPicker({ options, value, onChange }: Props) {
    return (
        <div role="radiogroup" aria-label="Trailer type" className="flex gap-2">
            {options.map((type) => {
                const selected = type.value === value;

                return (
                    <button
                        key={type.value}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        onClick={() => onChange(type.value)}
                        className={cn(
                            'h-12 flex-1 rounded-xl border px-3 text-sm font-semibold transition-colors',
                            selected
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'bg-card hover:bg-accent',
                        )}
                    >
                        {type.label}
                    </button>
                );
            })}
        </div>
    );
}
