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
import OutboxBanners from '@/components/outbox-banners';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import HoldingDialog from '@/components/unload/holding-dialog';
import WarehouseMapDialog from '@/components/unload/warehouse-map-dialog';
import { useBarcodeCamera } from '@/hooks/use-barcode-camera';
import { useUnloadRunner } from '@/hooks/use-unload-runner';
import type { ConsignmentProgress } from '@/hooks/use-unload-runner';
import { cn } from '@/lib/utils';

function progressMessage({
    item_count,
    in_bay,
    held,
    all_placed,
}: ConsignmentProgress): string {
    const heldNote = held > 0 ? `${held} held` : null;

    if (all_placed) {
        return [`All ${item_count} items placed`, heldNote]
            .filter(Boolean)
            .join(' · ');
    }

    return [`${in_bay} of ${item_count} in bay`, heldNote]
        .filter(Boolean)
        .join(' · ');
}

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
    const [acknowledgedId, setAcknowledgedId] = useState<string | null>(null);
    const [pickingLane, setPickingLane] = useState(false);
    const { direction, stage, override } = runner;
    const lane = override?.lane ?? direction?.bay.lane ?? null;
    const code = override?.code ?? direction?.bay.code;
    const dropTarget =
        direction?.bay.is_holding && lane ? `Lane ${lane}` : code;
    const needsHoldNotice =
        stage === 'directed' &&
        direction !== null &&
        direction.bay.is_holding &&
        acknowledgedId !== direction.handling_unit_id;

    return (
        <>
            <Head title="Unload" />

            <PageHeader
                title="Unload"
                subtitle="Scan a pallet, then take it to its bay"
                backHref="/dashboard"
                actions={<WarehouseMapDialog />}
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

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="w-full text-muted-foreground"
                        onClick={() => void runner.simulate()}
                        disabled={runner.busy || runner.simulating}
                    >
                        {runner.simulating
                            ? 'Simulating…'
                            : 'Simulate seeded scan'}
                    </Button>
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
                            {code}
                        </p>
                        <p className="text-lg text-muted-foreground">
                            {lane ? `Lane ${lane}` : direction.bay.name}
                        </p>
                        {override && direction.bay.lane && (
                            <p className="text-sm text-muted-foreground">
                                Lane {direction.bay.lane} was full
                            </p>
                        )}
                    </div>

                    <p className="text-lg font-medium">{direction.reason}</p>

                    {direction.bay.is_holding &&
                        direction.alternatives.length > 1 && (
                            <div className="space-y-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setPickingLane((v) => !v)}
                                    disabled={runner.busy}
                                >
                                    {pickingLane
                                        ? 'Hide lanes'
                                        : `Lane ${direction.bay.lane ?? ''} full? Use another lane`}
                                </Button>

                                {pickingLane && (
                                    <div
                                        className="grid grid-cols-5 gap-2"
                                        role="group"
                                        aria-label="Holding lanes"
                                    >
                                        {direction.alternatives.map(
                                            (option) => {
                                                const chosen =
                                                    (override?.location_id ??
                                                        direction.bay
                                                            .location_id) ===
                                                    option.location_id;

                                                return (
                                                    <Button
                                                        key={option.location_id}
                                                        type="button"
                                                        size="lg"
                                                        variant={
                                                            chosen
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                        aria-pressed={chosen}
                                                        disabled={runner.busy}
                                                        onClick={() =>
                                                            runner.chooseLane(
                                                                option.location_id ===
                                                                    direction
                                                                        .bay
                                                                        .location_id
                                                                    ? null
                                                                    : option,
                                                            )
                                                        }
                                                    >
                                                        {option.lane ??
                                                            option.code}
                                                    </Button>
                                                );
                                            },
                                        )}
                                    </div>
                                )}
                            </div>
                        )}

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
                            onClick={() => {
                                setPickingLane(false);
                                runner.reset();
                            }}
                            disabled={runner.busy}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            size="xl"
                            className="h-24 flex-1 flex-col gap-0"
                            onClick={() => void runner.confirm()}
                            disabled={runner.busy || needsHoldNotice}
                            aria-label={`Tap when dropped off in ${dropTarget}`}
                        >
                            <span className="flex items-center gap-2 text-sm font-medium opacity-90">
                                <PackageCheck className="size-4" />
                                {runner.busy
                                    ? 'Saving…'
                                    : 'Tap when dropped in'}
                            </span>
                            <span className="text-4xl font-black tracking-wide">
                                {dropTarget}
                            </span>
                        </Button>
                    </div>
                </section>
            )}

            <HoldingDialog
                direction={needsHoldNotice ? direction : null}
                onAcknowledge={() =>
                    setAcknowledgedId(direction?.handling_unit_id ?? null)
                }
                onCancel={runner.reset}
            />

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
                            Dropped in {code}
                            {lane ? ` (lane ${lane})` : ''}
                        </p>
                        <p className="text-lg text-muted-foreground">
                            {direction.connote_number} · piece{' '}
                            {direction.piece_number} of {direction.item_count}
                        </p>
                        {runner.queued ? (
                            <p className="text-lg font-semibold text-warning-foreground">
                                Saved on this device. It will send when you are
                                back online.
                            </p>
                        ) : (
                            <p
                                className={
                                    direction.progress.all_placed
                                        ? 'text-lg font-semibold text-success'
                                        : 'text-lg text-muted-foreground'
                                }
                            >
                                {progressMessage(direction.progress)}
                            </p>
                        )}
                    </div>
                    <Button
                        type="button"
                        size="xl"
                        className="w-full"
                        onClick={() => {
                            setPickingLane(false);
                            runner.reset();
                        }}
                    >
                        Scan next pallet
                    </Button>
                </section>
            )}

            {/* Below the card, so a banner appearing never moves what the
                runner is about to tap. */}
            <div className="mx-auto mt-4 w-full max-w-xl space-y-2 empty:hidden">
                <OutboxBanners outbox={runner.outbox} noun="drop" />
                {runner.problems.length > 0 && (
                    <div
                        role="alert"
                        className="space-y-1 rounded-xl bg-destructive px-4 py-3 text-white"
                    >
                        <p className="font-medium">
                            These drops could not be recorded. Tell a
                            supervisor:
                        </p>
                        {runner.problems.map((problem) => (
                            <p key={problem} className="text-sm">
                                {problem}
                            </p>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="mt-1 text-foreground"
                            onClick={runner.dismissProblems}
                        >
                            Dismiss
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}
