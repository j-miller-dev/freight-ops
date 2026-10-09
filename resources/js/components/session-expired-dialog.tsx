import { useState } from 'react';
import PinPad from '@/components/pin-pad';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { notifyAuthRestored, reauthenticate } from '@/lib/session';

type Props = {
    open: boolean;
    username: string | null;
    name: string;
    onRestored: () => void;
};

// Blocking on purpose: nothing works until the session is back. Nothing is
// lost meanwhile; queued scans stay on the device and send after sign-in.
export default function SessionExpiredDialog({
    open,
    username,
    name,
    onRestored,
}: Props) {
    const [pin, setPin] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    async function submit() {
        if (!username || pin.length < 4 || busy) {
            return;
        }

        setBusy(true);
        setError('');

        const failure = await reauthenticate(username, pin);

        setBusy(false);
        setPin('');

        if (failure) {
            setError(failure);

            return;
        }

        onRestored();
        notifyAuthRestored();
    }

    return (
        <Dialog open={open} onOpenChange={() => undefined}>
            <DialogContent
                showCloseButton={false}
                onEscapeKeyDown={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
            >
                <DialogHeader>
                    <DialogTitle className="text-xl">
                        Session expired
                    </DialogTitle>
                    <DialogDescription>
                        {username
                            ? `Enter the PIN for ${name} to carry on. Anything you scanned is saved on this device and will send automatically.`
                            : 'Sign in again to carry on. Anything you scanned is saved on this device.'}
                    </DialogDescription>
                </DialogHeader>

                {username ? (
                    <div className="space-y-4">
                        <PinPad
                            value={pin}
                            onChange={setPin}
                            onSubmit={() => void submit()}
                            disabled={busy}
                            invalid={error !== ''}
                        />

                        {error && (
                            <p className="text-center text-sm text-destructive">
                                {error}
                            </p>
                        )}

                        <Button
                            size="xl"
                            className="w-full"
                            disabled={pin.length < 4 || busy}
                            onClick={() => void submit()}
                        >
                            {busy && <Spinner />}
                            Continue
                        </Button>
                    </div>
                ) : null}

                <Button
                    variant="ghost"
                    className="w-full"
                    onClick={() => {
                        window.location.href = username
                            ? '/login'
                            : '/login/password';
                    }}
                >
                    {username ? 'Sign in as someone else' : 'Go to sign in'}
                </Button>
            </DialogContent>
        </Dialog>
    );
}
