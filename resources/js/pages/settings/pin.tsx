import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    username: string | null;
    hasPin: boolean;
};

const PIN_INPUT = {
    type: 'password',
    inputMode: 'numeric' as const,
    maxLength: 6,
    autoComplete: 'off',
    className: 'h-12 rounded-xl text-lg tracking-widest',
};

export default function Pin({ username, hasPin }: Props) {
    return (
        <>
            <Head title="PIN" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Sign-in PIN"
                    description={
                        username
                            ? `Use your username (${username}) and a 4 to 6 digit PIN to sign in quickly.`
                            : 'You have no username yet, so you cannot sign in with a PIN. Ask an admin to set one.'
                    }
                />

                <Form
                    action="/settings/pin"
                    method="put"
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    resetOnError={['current_pin', 'pin', 'pin_confirmation']}
                    className="space-y-5"
                >
                    {({ errors, processing, recentlySuccessful }) => (
                        <>
                            {hasPin && (
                                <div className="grid gap-2">
                                    <Label htmlFor="current_pin">
                                        Current PIN
                                    </Label>
                                    <Input
                                        id="current_pin"
                                        name="current_pin"
                                        {...PIN_INPUT}
                                    />
                                    <InputError message={errors.current_pin} />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="pin">New PIN</Label>
                                <Input id="pin" name="pin" {...PIN_INPUT} />
                                <InputError message={errors.pin} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="pin_confirmation">
                                    Confirm new PIN
                                </Label>
                                <Input
                                    id="pin_confirmation"
                                    name="pin_confirmation"
                                    {...PIN_INPUT}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    size="touch"
                                    disabled={processing}
                                    data-test="save-pin-button"
                                >
                                    Save PIN
                                </Button>
                                {recentlySuccessful && (
                                    <p className="text-sm font-medium text-success">
                                        Saved
                                    </p>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
