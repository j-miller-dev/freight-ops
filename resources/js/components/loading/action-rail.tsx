import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Tone = 'default' | 'success' | 'danger';

type RailButtonProps = {
    icon: LucideIcon;
    label: string;
    onClick: () => void;
    // A count or short status shown on the icon, e.g. "3/10".
    badge?: ReactNode;
    tone?: Tone;
    disabled?: boolean;
};

const TONE: Record<Tone, string> = {
    default: 'bg-card text-foreground hover:border-primary',
    success: 'border-success bg-success text-success-foreground',
    danger: 'border-destructive bg-destructive text-white',
};

export function RailButton({
    icon: Icon,
    label,
    onClick,
    badge,
    tone = 'default',
    disabled = false,
}: RailButtonProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className={cn(
                'relative flex h-20 w-24 shrink-0 flex-col items-center justify-center gap-1 rounded-2xl border px-1 text-xs font-medium transition active:scale-[0.97] disabled:opacity-50 disabled:active:scale-100 md:w-full',
                TONE[tone],
            )}
        >
            <Icon className="size-7" />
            <span className="max-w-full truncate">{label}</span>

            {badge !== undefined && badge !== null && badge !== false && (
                <span className="absolute -top-2 -right-2 min-w-6 rounded-full bg-primary px-1.5 py-0.5 text-center text-xs font-bold text-primary-foreground tabular-nums shadow-xs">
                    {badge}
                </span>
            )}
        </button>
    );
}

// Icon-only shortcuts to everything the manifest page can do besides scanning:
// a column beside the scan area on a tablet, a scrolling row on a phone.
export default function ActionRail({ children }: { children: ReactNode }) {
    return (
        <nav
            aria-label="Manifest actions"
            className="flex gap-3 overflow-x-auto pt-2 pr-2 pb-1 md:sticky md:top-20 md:w-24 md:shrink-0 md:flex-col md:self-start md:overflow-visible md:pt-0 md:pr-0"
        >
            {children}
        </nav>
    );
}
