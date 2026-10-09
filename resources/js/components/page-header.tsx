import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    title: string;
    subtitle?: ReactNode;
    backHref?: string;
    actions?: ReactNode;
};

export default function PageHeader({
    title,
    subtitle,
    backHref,
    actions,
}: Props) {
    return (
        <div className="mb-6 flex items-center gap-3">
            {backHref && (
                <Button
                    asChild
                    variant="outline"
                    size="icon"
                    className="size-12 shrink-0 rounded-xl"
                >
                    <Link href={backHref} aria-label="Back">
                        <ChevronLeft className="size-6" />
                    </Link>
                </Button>
            )}

            <div className="min-w-0 flex-1">
                <h1 className="truncate text-2xl font-bold tracking-tight">
                    {title}
                </h1>
                {subtitle && (
                    <p className="truncate text-sm text-muted-foreground">
                        {subtitle}
                    </p>
                )}
            </div>

            {actions && <div className="flex shrink-0 gap-2">{actions}</div>}
        </div>
    );
}
