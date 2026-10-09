import { router } from '@inertiajs/react';
import { Bell, Truck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getJson, postJson } from '@/lib/http';
import { cn } from '@/lib/utils';

type Item = {
    id: string;
    read: boolean;
    created_at: string | null;
    data: {
        manifest_id: string;
        manifest_number: string;
        trailer_name: string | null;
        finished_by: string;
        pallets_loaded: number;
        equipment: Record<string, number>;
    };
};

type Payload = { data: Item[]; unread_count: number };

const POLL_MS = 20_000;

function title(item: Item): string {
    return `${item.data.trailer_name ?? item.data.manifest_number} finished loading`;
}

// For the people who close and dispatch trailers: a bell with what the loaders
// have just finished. Polled, so it needs no extra infrastructure.
export default function NotificationBell() {
    const [items, setItems] = useState<Item[]>([]);
    const [unread, setUnread] = useState(0);
    const [menuOpen, setMenuOpen] = useState(false);
    const seen = useRef<number | null>(null);

    useEffect(() => {
        let active = true;

        function load() {
            getJson<Payload>('/notifications')
                .then((json) => {
                    if (!active) {
                        return;
                    }

                    // Pop up when something new arrives after the first load.
                    if (
                        seen.current !== null &&
                        json.unread_count > seen.current &&
                        json.data[0]
                    ) {
                        toast.info(title(json.data[0]), {
                            description: `${json.data[0].data.manifest_number} · ${json.data[0].data.finished_by}`,
                        });
                    }

                    seen.current = json.unread_count;
                    setItems(json.data);
                    setUnread(json.unread_count);
                })
                .catch(() => undefined);
        }

        load();
        const id = setInterval(load, POLL_MS);

        return () => {
            active = false;
            clearInterval(id);
        };
    }, []);

    function open(item: Item) {
        setMenuOpen(false);
        setItems((current) =>
            current.map((entry) =>
                entry.id === item.id ? { ...entry, read: true } : entry,
            ),
        );

        if (!item.read) {
            setUnread((count) => Math.max(0, count - 1));
            seen.current = Math.max(0, (seen.current ?? 1) - 1);
            void postJson(`/notifications/${item.id}/read`).catch(
                () => undefined,
            );
        }

        router.visit(`/loading/manifests/${item.data.manifest_id}`);
    }

    function markAllRead() {
        setItems((current) =>
            current.map((entry) => ({ ...entry, read: true })),
        );
        setUnread(0);
        seen.current = 0;
        void postJson('/notifications/read').catch(() => undefined);
    }

    return (
        <DropdownMenu open={menuOpen} onOpenChange={setMenuOpen}>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={`Notifications${unread ? `, ${unread} unread` : ''}`}
                    className="relative flex size-10 items-center justify-center rounded-full border bg-card hover:bg-accent"
                >
                    <Bell className="size-5" />
                    {unread > 0 && (
                        <span className="absolute -top-1 -right-1 flex min-w-5 items-center justify-center rounded-full bg-destructive px-1 text-xs font-bold text-white">
                            {unread}
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-96 p-0">
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <p className="font-semibold">Trailers finished</p>
                    {unread > 0 && (
                        <button
                            type="button"
                            onClick={markAllRead}
                            className="text-sm font-medium text-primary"
                        >
                            Mark all read
                        </button>
                    )}
                </div>

                {items.length === 0 ? (
                    <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                        Nothing yet. You will see a trailer here as soon as a
                        loader finishes it.
                    </p>
                ) : (
                    <ul className="max-h-96 overflow-y-auto">
                        {items.map((item) => (
                            <li key={item.id}>
                                <button
                                    type="button"
                                    onClick={() => open(item)}
                                    className={cn(
                                        'flex w-full items-start gap-3 border-b px-4 py-3 text-left last:border-0 hover:bg-accent',
                                        !item.read && 'bg-primary/5',
                                    )}
                                >
                                    <Truck className="mt-0.5 size-5 shrink-0 text-primary" />
                                    <span className="min-w-0 flex-1">
                                        <span
                                            className={cn(
                                                'block text-sm',
                                                !item.read && 'font-semibold',
                                            )}
                                        >
                                            {title(item)}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {item.data.manifest_number} ·{' '}
                                            {item.data.finished_by} ·{' '}
                                            {item.data.pallets_loaded} pallets
                                            {' · '}red{' '}
                                            {item.data.equipment.red_pallets ??
                                                0}
                                            {' / '}blue{' '}
                                            {item.data.equipment.blue_pallets ??
                                                0}
                                        </span>
                                        {item.created_at && (
                                            <span className="block text-xs text-muted-foreground">
                                                {new Date(
                                                    item.created_at,
                                                ).toLocaleTimeString([], {
                                                    hour: 'numeric',
                                                    minute: '2-digit',
                                                })}
                                            </span>
                                        )}
                                    </span>
                                    {!item.read && (
                                        <span className="mt-1.5 size-2.5 shrink-0 rounded-full bg-primary" />
                                    )}
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
