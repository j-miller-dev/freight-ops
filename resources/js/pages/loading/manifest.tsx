import { Head } from '@inertiajs/react';
import {
    Camera,
    CameraOff,
    ChevronRight,
    Keyboard,
    ListChecks,
    Truck,
    WifiOff,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import AlertError from '@/components/alert-error';
import ConsignmentSheet from '@/components/loading/consignment-sheet';
import DepartureBadge from '@/components/loading/departure-badge';
import ScanFeedback from '@/components/loading/scan-feedback';
import TrailerAlerts, {
    ClashBanner,
} from '@/components/loading/trailer-alerts';
import TrailerDialog from '@/components/loading/trailer-dialog';
import WarningDialog from '@/components/loading/warning-dialog';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useBarcodeCamera } from '@/hooks/use-barcode-camera';
import { useManifestScanner } from '@/hooks/use-manifest-scanner';
import { useManifestSummary } from '@/hooks/use-manifest-summary';
import { patchJson } from '@/lib/http';
import { trailerType } from '@/lib/trailer-types';
import { cn } from '@/lib/utils';
import type { ManifestSummary } from '@/types/loading';

type Props = {
    loader: { id: number; name: string };
    manifest: {
        id: string;
        manifest_number: string;
        service_date: string;
        departs_at: string | null;
        status: string;
        manifest_items_count: number;
        trailer_type: string;
    };
    destination: { id: string; code: string; name: string } | null;
    summary: ManifestSummary;
    bay_code: string | null;
};

export default function LoadingManifest({
    manifest,
    destination,
    summary: initialSummary,
}: Props) {
    const scanner = useManifestScanner(manifest.id);
    const videoRef = useRef<HTMLVideoElement>(null);
    const camera = useBarcodeCamera(videoRef, scanner.scan);
    const inputRef = useRef<HTMLInputElement>(null);
    const [keyboard, setKeyboard] = useState(false);
    const [trailer, setTrailer] = useState(manifest.trailer_type);
    const [trailerOpen, setTrailerOpen] = useState(false);
    const [consignmentsOpen, setConsignmentsOpen] = useState(false);
    const summary = useManifestSummary(
        manifest.id,
        initialSummary,
        scanner.result,
    );
    const trailerInfo = trailerType(trailer);

    const closed = manifest.status !== 'open';
    const { outbox } = scanner;
    const busy = scanner.scanning || scanner.simulating;
    const progress = scanner.result?.progress;

    // Hardware scanners type into whatever is focused, so the field has to be
    // ready again after every scan and after the warning dialog closes.
    useEffect(() => {
        if (!busy && !scanner.warning && !closed) {
            inputRef.current?.focus();
        }
    }, [busy, scanner.warning, closed]);

    async function chooseTrailer(value: string) {
        const previous = trailer;
        setTrailer(value);

        try {
            await patchJson(`/loading/manifests/${manifest.id}/trailer`, {
                trailer_type: value,
            });
        } catch {
            setTrailer(previous);
            toast.error('Could not change the trailer type. Try again.');
        }
    }

    return (
        <>
            <Head title={`Manifest ${manifest.manifest_number}`} />

            <PageHeader
                title={`Manifest ${manifest.manifest_number}`}
                subtitle={
                    destination
                        ? `${destination.code} · ${destination.name}`
                        : undefined
                }
                backHref={
                    destination
                        ? `/loading/depots/${destination.id}`
                        : '/loading'
                }
            />

            <div className="mb-4 space-y-2 empty:hidden">
                {!outbox.isOnline && (
                    <p className="flex items-center gap-2 rounded-xl bg-warning px-4 py-3 font-medium text-warning-foreground">
                        <WifiOff className="size-5" /> Offline — scans are
                        saving on this device.
                    </p>
                )}
                {outbox.pendingCount > 0 && (
                    <p className="rounded-xl bg-warning/30 px-4 py-3 font-medium">
                        {outbox.pendingCount}{' '}
                        {outbox.pendingCount === 1 ? 'scan' : 'scans'} waiting
                        to sync.
                    </p>
                )}
                {outbox.hasFailed && (
                    <p className="rounded-xl bg-destructive px-4 py-3 font-medium text-white">
                        Some scans could not be delivered. Tell a supervisor.
                    </p>
                )}
                {closed && (
                    <p className="rounded-xl bg-muted px-4 py-3 font-medium">
                        This manifest is closed. Scanning is disabled.
                    </p>
                )}
            </div>

            <ClashBanner summary={summary} />

            <section className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <DepartureBadge departsAt={manifest.departs_at} />

                <button
                    type="button"
                    onClick={() => setTrailerOpen(true)}
                    disabled={closed}
                    className="rounded-2xl border bg-card px-4 py-3 text-left transition hover:border-primary active:scale-[0.98] disabled:opacity-60"
                >
                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <Truck className="size-3.5" /> Trailer · tap to change
                    </p>
                    <p className="text-lg font-bold">{trailerInfo.label}</p>
                    <p className="text-xs text-muted-foreground">
                        {trailerInfo.capacity
                            ? `${trailerInfo.capacity} pallet positions`
                            : 'Layout to be confirmed'}
                    </p>
                </button>

                <button
                    type="button"
                    onClick={() => setConsignmentsOpen(true)}
                    className="rounded-2xl border bg-primary px-4 py-3 text-left text-primary-foreground transition active:scale-[0.98]"
                >
                    <p className="flex items-center gap-1.5 text-xs opacity-80">
                        <ListChecks className="size-3.5" /> Consignments
                    </p>
                    <p className="text-lg font-bold tabular-nums">
                        {summary.loaded_count}
                        {trailerInfo.capacity
                            ? ` / ${trailerInfo.capacity}`
                            : ''}{' '}
                        <span className="text-sm font-normal">pallets</span>
                    </p>
                    <p className="flex items-center text-xs opacity-80">
                        See what&apos;s on and what&apos;s left
                        <ChevronRight className="size-3.5" />
                    </p>
                </button>

                <TrailerAlerts summary={summary} />
            </section>

            <section className="mx-auto grid max-w-4xl gap-8 rounded-3xl border bg-card p-6 shadow-xs lg:grid-cols-2 lg:items-center">
                <div className="flex flex-col gap-4">
                    <div className="w-full space-y-3">
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
                                value={scanner.barcode}
                                onChange={(event) =>
                                    scanner.setBarcode(event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        event.preventDefault();
                                        scanner.scan(scanner.barcode);
                                    }
                                }}
                                placeholder="Barcode"
                                inputMode={keyboard ? 'text' : 'none'}
                                autoComplete="off"
                                autoCapitalize="characters"
                                disabled={busy || closed}
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
                                onClick={() => scanner.scan(scanner.barcode)}
                                disabled={
                                    !scanner.barcode.trim() || busy || closed
                                }
                            >
                                {scanner.scanning ? 'Loading…' : 'Load'}
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
                                    disabled={closed}
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
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="text-muted-foreground"
                        onClick={() => void scanner.simulate()}
                        disabled={busy || closed}
                    >
                        {scanner.simulating
                            ? 'Simulating…'
                            : 'Simulate seeded scan'}
                    </Button>
                </div>

                <div className="flex flex-col items-center gap-6 border-t pt-6 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-8">
                    <ScanFeedback
                        result={scanner.result}
                        error={scanner.error}
                        scanning={busy}
                    />

                    <div className="w-full space-y-2">
                        <div className="flex items-baseline justify-between">
                            <span className="text-sm text-muted-foreground">
                                Loaded on this manifest
                            </span>
                            <span className="text-xl font-bold tabular-nums">
                                {progress
                                    ? `${progress.loaded_count} / ${progress.total_count}`
                                    : manifest.manifest_items_count}
                            </span>
                        </div>

                        {progress && progress.total_count > 0 && (
                            <div
                                className="h-3 overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                aria-valuemin={0}
                                aria-valuemax={progress.total_count}
                                aria-valuenow={progress.loaded_count}
                            >
                                <div
                                    className="h-full rounded-full bg-success transition-all"
                                    style={{
                                        width: `${(progress.loaded_count / progress.total_count) * 100}%`,
                                    }}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </section>

            <TrailerDialog
                open={trailerOpen}
                value={trailer}
                onOpenChange={setTrailerOpen}
                onChange={(value) => void chooseTrailer(value)}
            />

            <ConsignmentSheet
                manifestId={manifest.id}
                manifestNumber={manifest.manifest_number}
                open={consignmentsOpen}
                onOpenChange={setConsignmentsOpen}
            />

            <WarningDialog
                warning={scanner.warning}
                busy={scanner.scanning}
                onConfirm={scanner.confirmWarning}
                onCancel={scanner.dismissWarning}
            />
        </>
    );
}
