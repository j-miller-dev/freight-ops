import { Head } from '@inertiajs/react';
import {
    Apple,
    Camera,
    CameraOff,
    Check,
    Keyboard,
    PackageCheck,
    ScanBarcode,
} from 'lucide-react';
import { useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import DgDiamond from '@/components/loading/dg-diamond';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useBarcodeCamera } from '@/hooks/use-barcode-camera';
import { useUnloadRunner } from '@/hooks/use-unload-runner';
import { cn } from '@/lib/utils';

export default function Unload() {
    const runner = useUnloadRunner();
    const videoRef = useRef<HTMLVideoElement>(null);
    const camera = useBarcodeCamera(videoRef, (value) => {
        if (runner.stage === 'idle') {
            void runner.lookup(value);
        }
    });
    const inputRef = useRef<HTMLInputElement>(null);
    const [keyboard, setKeyboard] = useState(false);
    const { direction, stage } = runner;

    return (
        <>
            <Head title="Unload" />

            <PageHeader
                title="Unload"
                subtitle="Scan a pallet, then take it to its bay"
                backHref="/dashboard"
            />

            {stage === 'idle' && (
                <section className="mx-auto w-full max-w-xl space-y-3 rounded-3xl border bg-card p-6 shadow-xs">
                    <div className="flex flex-col items-center gap-2 py-2 text-muted-foreground">
                        <ScanBarcode className="size-14" />
                        <p className="text-lg">Ready to scan</p>
                    </div>

                    <label
                        htmlFor="barcode"
                        className="block text-center text-sm font-medium text-muted-foreground"
                    >
                        Scan item ID
                    </label>

                    <div className="flex gap-2">
                        <Input
                            id="barcode"
                            ref={inputRef}
                            value={runner.barcode}
                            onChange={(event) =>
                                runner.setBarcode(event.target.value)
                            }
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    void runner.lookup(runner.barcode);
                                }
                            }}
                            placeholder="Barcode"
                            inputMode={keyboard ? 'text' : 'none'}
                            autoComplete="off"
                            autoCapitalize="characters"
                            disabled={runner.busy}
                            className="h-16 rounded-xl text-center text-2xl"
                        />

                        <Button
                            type="button"
                            variant={keyboard ? 'default' : 'outline'}
                            size="icon"
                            aria-label="Toggle on-screen keyboard"
                            aria-pressed={keyboard}
                            className="size-16 shrink-0 rounded-xl"
                            onClick={() => {
                                setKeyboard((value) => !value);
                                inputRef.current?.focus();
                            }}
                        >
                            <Keyboard className="size-6" />
                        </Button>
                    </div>

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            size="xl"
                            className="flex-1"
                            onClick={() => void runner.lookup(runner.barcode)}
                            disabled={!runner.barcode.trim() || runner.busy}
                        >
                            {runner.busy ? 'Looking up…' : 'Find bay'}
                        </Button>

                        {camera.supported && (
                            <Button
                                type="button"
                                variant="outline"
                                size="xl"
                                aria-label={
                                    camera.active
                                        ? 'Stop camera'
                                        : 'Scan with camera'
                                }
                                onClick={() =>
                                    camera.active
                                        ? camera.stop()
                                        : void camera.start()
                                }
                            >
                                {camera.active ? <CameraOff /> : <Camera />}
                            </Button>
                        )}
                    </div>

                    <div
                        className={cn(
                            'overflow-hidden rounded-xl bg-black',
                            !camera.active && 'hidden',
                        )}
                    >
                        <video
                            ref={videoRef}
                            className="aspect-video w-full object-cover"
                            muted
                            playsInline
                        />
                    </div>

                    {camera.error && <AlertError errors={[camera.error]} />}
                    {runner.error && <AlertError errors={[runner.error]} />}
                </section>
            )}

            {stage === 'directed' && direction && (
                <section
                    role="status"
                    aria-live="polite"
                    className="mx-auto w-full max-w-xl space-y-6 rounded-3xl border bg-card p-6 text-center shadow-xs"
                >
                    <div>
                        <p className="text-sm font-medium text-muted-foreground">
                            Take it to
                        </p>
                        <p
                            className={cn(
                                'text-7xl font-black tracking-wide',
                                direction.bay.is_holding
                                    ? 'text-warning'
                                    : 'text-primary',
                            )}
                        >
                            {direction.bay.code}
                        </p>
                        <p className="text-lg text-muted-foreground">
                            {direction.bay.name}
                        </p>
                    </div>

                    <p className="text-lg font-medium">{direction.reason}</p>

                    <div className="space-y-1 border-t pt-4">
                        <p className="text-lg font-medium">
                            {direction.connote_number}
                            <span className="font-normal text-muted-foreground">
                                {' '}
                                · piece {direction.piece_number} of{' '}
                                {direction.item_count}
                            </span>
                        </p>
                        {direction.sender_name && (
                            <p className="text-muted-foreground">
                                {direction.sender_name}
                            </p>
                        )}
                        <div className="flex items-center justify-center gap-2">
                            {direction.dg_class && (
                                <DgDiamond cls={direction.dg_class} />
                            )}
                            {direction.is_food && (
                                <Apple
                                    className="size-6 text-success"
                                    aria-label="Food"
                                />
                            )}
                        </div>
                        {direction.current_location_code && (
                            <p className="text-sm text-muted-foreground">
                                Currently in {direction.current_location_code}
                            </p>
                        )}
                    </div>

                    {runner.error && <AlertError errors={[runner.error]} />}

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="xl"
                            onClick={runner.reset}
                            disabled={runner.busy}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            size="xl"
                            className="flex-1"
                            onClick={() => void runner.confirm()}
                            disabled={runner.busy}
                        >
                            <PackageCheck />
                            {runner.busy ? 'Saving…' : 'Confirm dropped off'}
                        </Button>
                    </div>
                </section>
            )}

            {stage === 'dropped' && direction && (
                <section
                    role="status"
                    aria-live="polite"
                    className="mx-auto w-full max-w-xl space-y-6 rounded-3xl border bg-card p-6 text-center shadow-xs"
                >
                    <div className="mx-auto flex size-28 items-center justify-center rounded-full bg-success text-success-foreground">
                        <Check className="size-16" strokeWidth={3} />
                    </div>
                    <div className="space-y-1">
                        <p className="text-2xl font-bold text-success">
                            Dropped in {direction.bay.code}
                        </p>
                        <p className="text-lg text-muted-foreground">
                            {direction.connote_number} · piece{' '}
                            {direction.piece_number} of {direction.item_count}
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="xl"
                        className="w-full"
                        onClick={runner.reset}
                    >
                        Scan next pallet
                    </Button>
                </section>
            )}
        </>
    );
}
