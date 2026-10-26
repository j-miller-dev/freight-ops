import { Head } from '@inertiajs/react';
import {
    Boxes,
    Camera,
    CameraOff,
    CircleCheck,
    Keyboard,
    ListChecks,
    ListOrdered,
    Lock,
    Truck,
    TriangleAlert,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import AlertError from '@/components/alert-error';
import ActionRail, { RailButton } from '@/components/loading/action-rail';
import CloseManifestDialog from '@/components/loading/close-manifest-dialog';
import ConsignmentSheet from '@/components/loading/consignment-sheet';
import DepartureBadge from '@/components/loading/departure-badge';
import EquipmentDialog from '@/components/loading/equipment-dialog';
import FinishDialog from '@/components/loading/finish-dialog';
import NextUpSheet from '@/components/loading/next-up-sheet';
import ScanFeedback from '@/components/loading/scan-feedback';
import TrailerAlerts, {
    ClashBanner,
} from '@/components/loading/trailer-alerts';
import TrailerDialog from '@/components/loading/trailer-dialog';
import TrailerMapDialog from '@/components/loading/trailer-map-dialog';
import WarningDialog from '@/components/loading/warning-dialog';
import OutboxBanners from '@/components/outbox-banners';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useBarcodeCamera } from '@/hooks/use-barcode-camera';
import { useEquipment } from '@/hooks/use-equipment';
import { useManifestScanner } from '@/hooks/use-manifest-scanner';
import { useManifestSummary } from '@/hooks/use-manifest-summary';
import { deleteJson, patchJson, postJson } from '@/lib/http';
import { cn } from '@/lib/utils';
import type {
    ClosedState,
    DgItem,
    EquipmentCounts,
    EquipmentItemDef,
    FinishedState,
    ManifestSummary,
    TrailerInfo,
    TrailerPosition,
    TrailerTypeOption,
} from '@/types/loading';

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
    trailer_types: TrailerTypeOption[];
    trailer: TrailerInfo | null;
    finished: FinishedState;
    closed: ClosedState;
    can_close: boolean;
    equipment: EquipmentCounts;
    equipment_items: EquipmentItemDef[];
};

export default function LoadingManifest({
    manifest,
    destination,
    summary: initialSummary,
    trailer_types: trailerTypes,
    trailer: trailerInfoProp,
    finished: initialFinished,
    closed: initialClosed,
    can_close: canClose,
    equipment: initialEquipment,
    equipment_items: equipmentItems,
}: Props) {
    const scanner = useManifestScanner(manifest.id);
    const videoRef = useRef<HTMLVideoElement>(null);
    const camera = useBarcodeCamera(videoRef, scanner.scan);
    const inputRef = useRef<HTMLInputElement>(null);
    const [keyboard, setKeyboard] = useState(false);
    const [trailer, setTrailer] = useState(manifest.trailer_type);
    const [trailerOpen, setTrailerOpen] = useState(false);
    const [consignmentsOpen, setConsignmentsOpen] = useState(false);
    const [nextUpOpen, setNextUpOpen] = useState(false);
    const { summary, refresh } = useManifestSummary(
        manifest.id,
        initialSummary,
        scanner.result,
    );
    const trailerInfo =
        trailerTypes.find((type) => type.value === trailer) ?? trailerTypes[0];

    // DG placement: the map opens by itself when a DG pallet is scanned, or on
    // demand from the "On board" tile. It is derived from the scan result, so
    // dismissing it only suppresses that one pallet.
    const [mapOpen, setMapOpen] = useState(false);
    const [dismissedFor, setDismissedFor] = useState<string | null>(null);
    const [activeId, setActiveId] = useState<string | null>(null);
    const scanned = scanner.result?.dgClass ? scanner.result : null;
    const scannedId = scanned?.handlingUnitId ?? null;
    const autoOpen =
        scannedId !== null && dismissedFor !== scannedId && !scanner.warning;
    const mapVisible = mapOpen || autoOpen;

    // The pallet that was just scanned may not be in the summary yet.
    const justLoaded: DgItem | null =
        scanned && scannedId
            ? (summary.dg_items.find((item) => item.id === scannedId) ?? {
                  id: scannedId,
                  barcode: scanned.barcode,
                  piece_number: scanned.pieceNumber ?? 0,
                  dg_class: scanned.dgClass ?? '',
                  un_number: scanned.unNumber ?? null,
                  proper_shipping_name: scanned.properShippingName ?? null,
                  connote_number: scanned.connoteNumber ?? '',
                  position: null,
              })
            : null;
    const dgItems =
        justLoaded &&
        !summary.dg_items.some((item) => item.id === justLoaded.id)
            ? [...summary.dg_items, justLoaded]
            : summary.dg_items;
    const defaultActiveId = autoOpen
        ? scannedId
        : (dgItems.find((item) => !item.position)?.id ??
          dgItems[0]?.id ??
          null);
    const activeItem =
        dgItems.find((item) => item.id === (activeId ?? defaultActiveId)) ??
        null;

    const { counts, setCounts, adjust } = useEquipment(
        manifest.id,
        initialEquipment,
    );
    const [finished, setFinished] = useState(initialFinished);
    const [finishOpen, setFinishOpen] = useState(false);
    const [equipmentOpen, setEquipmentOpen] = useState(false);
    const [status, setStatus] = useState(manifest.status);
    const [closedState, setClosedState] = useState(initialClosed);
    const [closeOpen, setCloseOpen] = useState(false);

    // A manifest the scaler closed, or one the loader has signed off, takes no
    // more scans (reopen to carry on).
    const closed = status !== 'open' || finished !== null;
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
            const json = await patchJson<{
                data: { positions_cleared: number };
            }>(`/loading/manifests/${manifest.id}/trailer`, {
                trailer_type: value,
            });

            if (json.data.positions_cleared > 0) {
                toast.info(
                    'Trailer positions were cleared for the new layout.',
                );
                void refresh();
            }
        } catch {
            setTrailer(previous);
            toast.error('Could not change the trailer type. Try again.');
        }
    }

    async function placeItem(
        item: DgItem,
        position: TrailerPosition | null,
    ): Promise<boolean> {
        try {
            await patchJson(
                `/loading/manifests/${manifest.id}/pallets/${item.id}/position`,
                position ?? { unit: null, row: null, side: null },
            );
            await refresh();

            return true;
        } catch (error) {
            toast.error((error as Error).message);

            return false;
        }
    }

    async function finishLoading(equipment: EquipmentCounts): Promise<boolean> {
        try {
            const json = await postJson<{
                data: { finished: FinishedState; equipment: EquipmentCounts };
            }>(`/loading/manifests/${manifest.id}/finish`, { equipment });

            setFinished(json.data.finished);
            setCounts(json.data.equipment);
            setFinishOpen(false);
            toast.success('Trailer finished. The scaler has been told.');

            return true;
        } catch (error) {
            toast.error((error as Error).message);

            return false;
        }
    }

    async function reopenLoading() {
        try {
            await deleteJson(`/loading/manifests/${manifest.id}/finish`);
            setFinished(null);
        } catch (error) {
            toast.error((error as Error).message);
        }
    }

    async function closeManifest(): Promise<boolean> {
        try {
            const json = await postJson<{
                data: {
                    status: string;
                    closed_at: string;
                    closed_by: string | null;
                };
            }>(`/loading/manifests/${manifest.id}/close`, {
                acknowledged: true,
            });

            setStatus(json.data.status);
            setClosedState({
                at: json.data.closed_at,
                by: json.data.closed_by,
            });
            toast.success('Manifest closed.');

            return true;
        } catch (error) {
            toast.error((error as Error).message);

            return false;
        }
    }

    function handleMapOpenChange(open: boolean) {
        setMapOpen(open);

        if (!open) {
            setActiveId(null);

            if (scannedId) {
                setDismissedFor(scannedId);
            }
        }
    }

    return (
        <>
            <Head title={`Manifest ${manifest.manifest_number}`} />

            <PageHeader
                title={`Manifest ${manifest.manifest_number}`}
                subtitle={[
                    trailerInfoProp?.name,
                    destination && `${destination.code} · ${destination.name}`,
                ]
                    .filter(Boolean)
                    .join(' · ')}
                backHref={
                    destination
                        ? `/loading/depots/${destination.id}`
                        : '/loading'
                }
                actions={
                    <DepartureBadge departsAt={manifest.departs_at} compact />
                }
            />

            <div className="flex flex-col gap-4 md:flex-row md:items-start">
                <ActionRail>
                    <RailButton
                        icon={ListChecks}
                        label="Consignments"
                        badge={`${summary.consignments_complete}/${summary.consignments_total}`}
                        onClick={() => setConsignmentsOpen(true)}
                    />
                    <RailButton
                        icon={ListOrdered}
                        label="Next up"
                        onClick={() => setNextUpOpen(true)}
                        disabled={closed}
                    />
                    <RailButton
                        icon={Truck}
                        label={trailerInfo.label}
                        onClick={() => setTrailerOpen(true)}
                        disabled={closed}
                    />
                    <RailButton
                        icon={TriangleAlert}
                        label="DG map"
                        badge={summary.dg_items.length || undefined}
                        tone={
                            summary.food_conflicts.length > 0
                                ? 'danger'
                                : 'default'
                        }
                        onClick={() => setMapOpen(true)}
                        disabled={summary.dg_items.length === 0}
                    />
                    <RailButton
                        icon={Boxes}
                        label="Equipment"
                        onClick={() => setEquipmentOpen(true)}
                        disabled={closed}
                    />
                    {status === 'open' && !finished && (
                        <RailButton
                            icon={CircleCheck}
                            label="Finish"
                            tone="success"
                            onClick={() => setFinishOpen(true)}
                        />
                    )}
                    {canClose && status === 'open' && (
                        <RailButton
                            icon={Lock}
                            label="Close"
                            onClick={() => setCloseOpen(true)}
                        />
                    )}
                </ActionRail>
                <div className="min-w-0 flex-1">
                    <div className="mb-4 space-y-2 empty:hidden">
                        <OutboxBanners outbox={outbox} noun="scan" />
                        {status !== 'open' && (
                            <p className="rounded-xl bg-muted px-4 py-3 font-medium">
                                This manifest is closed
                                {closedState?.by ? ` by ${closedState.by}` : ''}
                                {closedState?.at
                                    ? ` at ${new Date(closedState.at).toLocaleString([], { hour: 'numeric', minute: '2-digit' })}`
                                    : ''}
                                . Scanning is disabled.
                            </p>
                        )}
                    </div>

                    <ClashBanner summary={summary} />

                    <button
                        type="button"
                        onClick={() => setMapOpen(true)}
                        disabled={summary.dg_items.length === 0}
                        aria-label="Show dangerous goods on the trailer map"
                        className="mb-4 block text-left empty:hidden disabled:cursor-default"
                    >
                        <TrailerAlerts summary={summary} strip />
                    </button>

                    {finished && (
                        <section className="mb-4 flex flex-wrap items-center gap-3 rounded-2xl bg-success/15 px-4 py-3">
                            <CircleCheck className="size-6 shrink-0 text-success" />
                            <p className="min-w-0 flex-1 font-medium">
                                Loading finished
                                {finished.by
                                    ? ` by ${finished.by}`
                                    : ''} at{' '}
                                {new Date(finished.at).toLocaleTimeString([], {
                                    hour: 'numeric',
                                    minute: '2-digit',
                                })}
                                . The scaler has been told.
                            </p>
                            {status === 'open' && (
                                <Button
                                    variant="outline"
                                    className="h-11 rounded-xl"
                                    onClick={() => void reopenLoading()}
                                >
                                    Reopen to keep loading
                                </Button>
                            )}
                        </section>
                    )}

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
                                            scanner.setBarcode(
                                                event.target.value,
                                            )
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
                                        variant={
                                            keyboard ? 'default' : 'outline'
                                        }
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
                                        onClick={() =>
                                            scanner.scan(scanner.barcode)
                                        }
                                        disabled={
                                            !scanner.barcode.trim() ||
                                            busy ||
                                            closed
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
                                            {camera.active ? (
                                                <CameraOff />
                                            ) : (
                                                <Camera />
                                            )}
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

                                {camera.error && (
                                    <AlertError errors={[camera.error]} />
                                )}
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
                </div>
            </div>

            <EquipmentDialog
                open={equipmentOpen}
                onOpenChange={setEquipmentOpen}
                items={equipmentItems}
                counts={counts}
                onAdjust={(key, delta) => void adjust(key, delta)}
            />

            <FinishDialog
                open={finishOpen}
                onOpenChange={setFinishOpen}
                trailerName={trailerInfoProp?.name ?? manifest.manifest_number}
                items={equipmentItems}
                counts={counts}
                clash={summary.food_conflicts.length > 0}
                onFinish={finishLoading}
            />

            <TrailerDialog
                options={trailerTypes}
                open={trailerOpen}
                value={trailer}
                onOpenChange={setTrailerOpen}
                onChange={(value) => void chooseTrailer(value)}
            />

            <TrailerMapDialog
                open={mapVisible}
                onOpenChange={handleMapOpenChange}
                trailer={trailerInfo}
                items={dgItems}
                active={activeItem}
                onActiveChange={setActiveId}
                onPlace={placeItem}
                justLoaded={autoOpen ? justLoaded : null}
            />

            <NextUpSheet
                manifestId={manifest.id}
                manifestNumber={manifest.manifest_number}
                open={nextUpOpen}
                onOpenChange={setNextUpOpen}
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

            <CloseManifestDialog
                open={closeOpen}
                onOpenChange={setCloseOpen}
                manifestNumber={manifest.manifest_number}
                consignmentsTotal={summary.consignments_total}
                consignmentsComplete={summary.consignments_complete}
                loadingFinished={finished !== null}
                onClose={closeManifest}
            />
        </>
    );
}
