import { Delete } from 'lucide-react';
import { useEffect } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    value: string;
    onChange: (value: string) => void;
    onSubmit: () => void;
    maxLength?: number;
    disabled?: boolean;
    invalid?: boolean;
};

const KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9'];

export default function PinPad({
    value,
    onChange,
    onSubmit,
    maxLength = 6,
    disabled = false,
    invalid = false,
}: Props) {
    // Desktop and bluetooth keyboards work too, as long as the user is not
    // typing into a field (the username).
    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            const target = event.target as HTMLElement | null;

            if (
                disabled ||
                target?.closest('input, textarea, select, [contenteditable]')
            ) {
                return;
            }

            if (/^\d$/.test(event.key) && value.length < maxLength) {
                onChange(value + event.key);
            } else if (event.key === 'Backspace') {
                onChange(value.slice(0, -1));
            } else if (event.key === 'Enter') {
                onSubmit();
            }
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [value, maxLength, disabled, onChange, onSubmit]);

    function press(digit: string) {
        if (!disabled && value.length < maxLength) {
            onChange(value + digit);
        }
    }

    const keyClass =
        'flex h-16 items-center justify-center rounded-2xl border bg-card text-2xl font-semibold shadow-xs transition active:scale-95 active:bg-accent disabled:opacity-50';

    return (
        <div className="space-y-5">
            <div
                role="status"
                aria-label={`${value.length} digits entered`}
                className="flex justify-center gap-3"
            >
                {Array.from({ length: maxLength }, (_, index) => (
                    <span
                        key={index}
                        className={cn(
                            'size-4 rounded-full border-2 transition-colors',
                            index < value.length
                                ? invalid
                                    ? 'border-destructive bg-destructive'
                                    : 'border-primary bg-primary'
                                : 'border-muted-foreground/40',
                        )}
                    />
                ))}
            </div>

            <div className="mx-auto grid max-w-xs grid-cols-3 gap-3">
                {KEYS.map((digit) => (
                    <button
                        key={digit}
                        type="button"
                        className={keyClass}
                        disabled={disabled}
                        onClick={() => press(digit)}
                    >
                        {digit}
                    </button>
                ))}

                <button
                    type="button"
                    className={cn(keyClass, 'text-base font-medium')}
                    disabled={disabled || value.length === 0}
                    onClick={() => onChange('')}
                >
                    Clear
                </button>
                <button
                    type="button"
                    className={keyClass}
                    disabled={disabled}
                    onClick={() => press('0')}
                >
                    0
                </button>
                <button
                    type="button"
                    aria-label="Delete last digit"
                    className={keyClass}
                    disabled={disabled || value.length === 0}
                    onClick={() => onChange(value.slice(0, -1))}
                >
                    <Delete className="size-6" />
                </button>
            </div>
        </div>
    );
}
