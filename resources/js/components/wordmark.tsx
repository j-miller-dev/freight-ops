import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

export default function Wordmark({ className }: { className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-2.5', className)}>
            <span className="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm">
                <AppLogoIcon className="size-5 fill-current" />
            </span>

            <span className="text-xl leading-none tracking-tight select-none">
                <span className="font-semibold">freight</span>
                <span className="font-black text-primary italic">OPS</span>
            </span>
        </span>
    );
}
