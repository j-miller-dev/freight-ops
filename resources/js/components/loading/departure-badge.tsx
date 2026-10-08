import { Clock } from 'lucide-react';
import { useNow } from '@/hooks/use-now';
import { cn } from '@/lib/utils';

function span(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;

    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

export default function DepartureBadge({
    departsAt,
}: {
    departsAt: string | null;
}) {
    const now = useNow();

    if (!departsAt) {
        return (
            <div className="rounded-2xl border bg-card px-4 py-3">
                <p className="text-xs text-muted-foreground">Departs</p>
                <p className="text-lg font-semibold text-muted-foreground">
                    Not set
                </p>
            </div>
        );
    }

    const departure = new Date(departsAt);
    const minutes = Math.round((departure.getTime() - now) / 60_000);
    const departed = minutes < 0;
    const tone = departed
        ? 'bg-muted text-muted-foreground'
        : minutes <= 30
          ? 'bg-destructive text-white'
          : minutes <= 120
            ? 'bg-warning text-warning-foreground'
            : 'bg-card';

    return (
        <div className={cn('rounded-2xl border px-4 py-3', tone)}>
            <p className="flex items-center gap-1.5 text-xs opacity-80">
                <Clock className="size-3.5" /> Departs
            </p>
            <p className="text-lg leading-tight font-bold tabular-nums">
                {departure.toLocaleTimeString([], {
                    hour: 'numeric',
                    minute: '2-digit',
                })}
            </p>
            <p className="text-xs opacity-80">
                {departed ? `${span(-minutes)} ago` : `in ${span(minutes)}`}
            </p>
        </div>
    );
}
