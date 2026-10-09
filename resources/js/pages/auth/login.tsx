import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PinPad from '@/components/pin-pad';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    status?: string;
};

export default function Login({ status }: Props) {
    const form = useForm({ username: '', pin: '' });
    const canSubmit =
        form.data.username.trim() !== '' &&
        form.data.pin.length >= 4 &&
        !form.processing;

    function submit() {
        if (!canSubmit) {
            return;
        }

        form.post('/login/pin', {
            onFinish: () => form.reset('pin'),
        });
    }

    return (
        <>
            <Head title="Log in" />

            {/* Breaks out of the narrow auth card on wide screens so the keypad
                and the Sign in button both fit on a tablet. */}
            <div className="grid gap-6 md:relative md:left-1/2 md:w-[42rem] md:-translate-x-1/2 md:grid-cols-2 md:items-center md:gap-10">
                <div className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="username">Username</Label>
                        <Input
                            id="username"
                            value={form.data.username}
                            onChange={(event) =>
                                form.setData('username', event.target.value)
                            }
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    submit();
                                }
                            }}
                            autoFocus
                            autoCapitalize="none"
                            autoCorrect="off"
                            autoComplete="username"
                            placeholder="first.last"
                            className="h-14 rounded-xl text-lg"
                        />
                    </div>

                    <InputError
                        className="min-h-5 text-center"
                        message={form.errors.pin ?? form.errors.username}
                    />

                    <Button
                        type="button"
                        size="xl"
                        className="hidden w-full md:inline-flex"
                        disabled={!canSubmit}
                        onClick={submit}
                        data-test="login-button"
                    >
                        {form.processing && <Spinner />}
                        Sign in
                    </Button>

                    <p className="hidden text-center text-sm text-muted-foreground md:block">
                        <TextLink href="/login/password">
                            Use email and password instead
                        </TextLink>
                    </p>
                </div>

                <PinPad
                    value={form.data.pin}
                    onChange={(pin) => form.setData('pin', pin)}
                    onSubmit={submit}
                    disabled={form.processing}
                    invalid={Boolean(form.errors.pin)}
                />

                <div className="space-y-4 md:hidden">
                    <Button
                        type="button"
                        size="xl"
                        className="w-full"
                        disabled={!canSubmit}
                        onClick={submit}
                    >
                        {form.processing && <Spinner />}
                        Sign in
                    </Button>
                    <p className="text-center text-sm text-muted-foreground">
                        <TextLink href="/login/password">
                            Use email and password instead
                        </TextLink>
                    </p>
                </div>

                {status && (
                    <p className="text-center text-sm font-medium text-green-600 md:col-span-2">
                        {status}
                    </p>
                )}
            </div>
        </>
    );
}

Login.layout = {
    title: 'Welcome back',
    description: 'Enter your username and PIN',
};
