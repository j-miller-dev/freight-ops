import { WifiOff } from 'lucide-react';
import type { OutboxState } from '@/hooks/use-outbox';

type Props = {
    outbox: Pick<OutboxState, 'isOnline' | 'pendingCount' | 'hasFailed'>;
    // What is waiting in the queue, e.g. "scan" or "drop".
    noun: string;
};

// What the device's queue is doing, for any screen that saves work offline.
export default function OutboxBanners({ outbox, noun }: Props) {
    return (
        <>
            {!outbox.isOnline && (
                <p className="flex items-center gap-2 rounded-xl bg-warning px-4 py-3 font-medium text-warning-foreground">
                    <WifiOff className="size-5" /> Offline — {noun}s are saving
                    on this device.
                </p>
            )}
            {outbox.pendingCount > 0 && (
                <p className="rounded-xl bg-warning/30 px-4 py-3 font-medium">
                    {outbox.pendingCount}{' '}
                    {outbox.pendingCount === 1 ? noun : `${noun}s`} waiting to
                    sync.
                </p>
            )}
            {outbox.hasFailed && (
                <p className="rounded-xl bg-destructive px-4 py-3 font-medium text-white">
                    Some {noun}s could not be delivered. Tell a supervisor.
                </p>
            )}
        </>
    );
}
