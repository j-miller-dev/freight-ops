import { Link, usePage } from '@inertiajs/react';
import { House, Truck, UserRound, WifiOff } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import NotificationBell from '@/components/notification-bell';
import SessionExpiredDialog from '@/components/session-expired-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import Wordmark from '@/components/wordmark';
import { useOnlineStatus } from '@/hooks/use-online-status';
import { onAuthExpired } from '@/lib/session';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type FooterItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    // Path prefixes that keep this item highlighted.
    match: string[];
};

// Roles that close and dispatch trailers, and so get "trailer finished" alerts.
const ALERT_ROLES = ['scaler', 'supervisor', 'admin'];

const FOOTER_ITEMS: FooterItem[] = [
    {
        title: 'Home',
        href: dashboard().url,
        icon: House,
        match: ['/dashboard'],
    },
    { title: 'Load PUD', href: '/loading', icon: Truck, match: ['/loading'] },
    {
        title: 'Account',
        href: '/settings/profile',
        icon: UserRound,
        match: ['/settings'],
    },
];

export default function KioskLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage().props;
    const { url } = usePage();
    const online = useOnlineStatus();
    const [sessionExpired, setSessionExpired] = useState(false);

    useEffect(() => onAuthExpired(() => setSessionExpired(true)), []);
    const path = url.split('?')[0];

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="sticky top-0 z-10 flex h-14 items-center justify-between border-b bg-card/90 px-4 backdrop-blur">
                <Link href={dashboard().url} aria-label="Home">
                    <Wordmark />
                </Link>

                <div className="flex items-center gap-3 text-sm">
                    {!online && (
                        <span className="flex items-center gap-1.5 rounded-full bg-warning px-3 py-1 font-medium text-warning-foreground">
                            <WifiOff className="size-4" /> Offline
                        </span>
                    )}
                    {ALERT_ROLES.includes(auth.user.role ?? '') && (
                        <NotificationBell />
                    )}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className="flex h-10 items-center gap-2 rounded-full border bg-card px-3 text-sm font-medium hover:bg-accent"
                            >
                                <span className="flex size-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
                                    {auth.user.name.charAt(0).toUpperCase()}
                                </span>
                                <span className="hidden sm:inline">
                                    {auth.user.name}
                                </span>
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="min-w-56">
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </header>

            <main className="mx-auto w-full max-w-5xl flex-1 p-4 pb-28 sm:p-6 sm:pb-28">
                {children}
            </main>

            <SessionExpiredDialog
                open={sessionExpired}
                username={auth.user.username ?? null}
                name={auth.user.name}
                onRestored={() => setSessionExpired(false)}
            />

            <nav
                aria-label="Primary"
                className="fixed inset-x-0 bottom-0 z-10 border-t bg-card/95 pb-[env(safe-area-inset-bottom)] backdrop-blur"
            >
                <ul className="mx-auto flex max-w-xl">
                    {FOOTER_ITEMS.map((item) => {
                        const active = item.match.some((prefix) =>
                            path.startsWith(prefix),
                        );

                        return (
                            <li key={item.title} className="flex-1">
                                <Link
                                    href={item.href}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'flex h-16 flex-col items-center justify-center gap-1 text-xs font-medium transition-colors',
                                        active
                                            ? 'text-primary'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex h-8 w-14 items-center justify-center rounded-full transition-colors',
                                            active && 'bg-primary/12',
                                        )}
                                    >
                                        <item.icon className="size-6" />
                                    </span>
                                    {item.title}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>
        </div>
    );
}
