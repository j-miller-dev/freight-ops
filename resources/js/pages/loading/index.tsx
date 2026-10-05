import { Head } from '@inertiajs/react';
import { CameraIcon, CameraOffIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import AlertError from '@/components/alert-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import {
    BarcodeDetectionScanner,
    isBarcodeDetectionSupported,
} from '@/lib/barcode-scanner';
import { cn } from '@/lib/utils';

type Destination = {
    id: string;
    code: string;
    name: string;
};

type Manifest = {
    id: string;
    manifest_number: string;
    service_date: string;
    status: string;
    manifest_items_count: number;
};

type ScanResult = {
    barcode: string;
    connoteNumber?: string;
    pieceNumber?: number;
    loader?: string;
    scannedAt?: string;
    progress?: {
        loaded_count: number;
        total_count: number;
    };
    consignmentProgress?: {
        loaded_count: number;
        total_count: number;
    };
};

type PendingScan = {
    barcode: string;
    clientEventId: string;
    occurredAt: string;
    acknowledgedWarnings: string[];
};

type WarningPrompt = PendingScan & {
    code: 'destination_mismatch' | 'consignment_split' | 'already_assigned';
    message: string;
    details?: Record<string, unknown>;
};

type Props = {
    loader: {
        id: number;
        name: string;
    };
    destinations: Destination[];
};

// Mirrors HandlingUnitStatus::Loaded->color() on the backend, so a loaded
// pallet reads the same color here as it will everywhere else in the app.
const LOADED_BADGE_CLASS =
    'border-transparent bg-yellow-100 text-yellow-900 dark:bg-yellow-500/20 dark:text-yellow-300';

export default function Loading({ loader, destinations }: Props) {
    const [destinationId, setDestinationId] = useState('');
    const [manifestId, setManifestId] = useState('');
    const [barcode, setBarcode] = useState('');
    const [manifests, setManifests] = useState<Manifest[]>([]);
    const [loading, setLoading] = useState(false);
    const [scanning, setScanning] = useState(false);
    const [simulating, setSimulating] = useState(false);
    const [error, setError] = useState('');
    const [scanResult, setScanResult] = useState<ScanResult | null>(null);
    const [warningPrompt, setWarningPrompt] = useState<WarningPrompt | null>(
        null,
    );
    const [cameraActive, setCameraActive] = useState(false);
    const [cameraError, setCameraError] = useState('');

    const videoRef = useRef<HTMLVideoElement>(null);
    const scannerRef = useRef<BarcodeDetectionScanner | null>(null);
    const busyRef = useRef(false);

    function selectDestination(value: string) {
        setDestinationId(value);
        setManifestId('');
        setManifests([]);
        setError('');
        setScanResult(null);
        setWarningPrompt(null);
    }

    useEffect(() => {
        if (!destinationId) {
            return;
        }

        const controller = new AbortController();

        async function fetchManifests() {
            setLoading(true);

            try {
                const params = new URLSearchParams({
                    destination_id: destinationId,
                });

                const response = await fetch(
                    `/loading/manifests?${params.toString()}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error('Unable to load manifests.');
                }

                const json = await response.json();
                setManifests(json.data);
            } catch (fetchError) {
                if ((fetchError as Error).name !== 'AbortError') {
                    setError('Unable to load manifests.');
                }
            } finally {
                setLoading(false);
            }
        }

        fetchManifests();

        return () => controller.abort();
    }, [destinationId]);

    // Camera scanning is tied to a single manifest; switching manifests (or
    // unmounting) means a deliberate restart rather than silently scanning
    // the old one.
    useEffect(() => {
        return () => stopCamera();
    }, [manifestId]);

    function stopCamera() {
        scannerRef.current?.stop();
        scannerRef.current = null;
        setCameraActive(false);
    }

    async function startCamera() {
        if (!videoRef.current) {
            return;
        }

        setCameraError('');

        const scanner = new BarcodeDetectionScanner();
        scannerRef.current = scanner;

        scanner.onScan((value) => {
            if (busyRef.current) {
                return;
            }

            setBarcode(value);
            void scanBarcode(value);
        });

        try {
            await scanner.start(videoRef.current);
            setCameraActive(true);
        } catch {
            setCameraError(
                'Camera access was denied or is unavailable. Use manual entry below.',
            );
            stopCamera();
        }
    }

    async function submitScan(scan: PendingScan) {
        if (!manifestId || !scan.barcode) {
            return;
        }

        busyRef.current = true;
        setScanning(true);
        setError('');
        setScanResult(null);

        const xsrfToken = document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        try {
            const response = await fetch(
                `/loading/manifests/${manifestId}/scan`,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(xsrfToken
                            ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) }
                            : {}),
                    },
                    body: JSON.stringify({
                        barcode: scan.barcode,
                        client_event_id: scan.clientEventId,
                        occurred_at: scan.occurredAt,
                        acknowledged_warnings: scan.acknowledgedWarnings,
                    }),
                },
            );

            const json = await response.json();

            if (!response.ok) {
                const warningCodes = [
                    'destination_mismatch',
                    'consignment_split',
                    'already_assigned',
                ] as const;

                if (warningCodes.includes(json.error?.code)) {
                    setWarningPrompt({
                        ...scan,
                        code: json.error.code,
                        message:
                            json.error.message ??
                            'This scan needs confirmation.',
                        details: json.error.details,
                    });

                    return;
                }

                throw new Error(
                    json.error?.message ?? 'Unable to load this pallet.',
                );
            }

            setBarcode('');
            setWarningPrompt(null);

            const result: ScanResult = {
                barcode: scan.barcode,
                connoteNumber: json.data.connote_number,
                pieceNumber: json.data.piece_number,
                loader: json.data.loader?.name,
                scannedAt: json.data.loaded_at,
                progress: json.data.progress,
                consignmentProgress: json.data.consignment_progress,
            };

            setScanResult(result);
            toast.success(`Loaded ${result.connoteNumber ?? result.barcode}`, {
                description: result.consignmentProgress
                    ? `${result.consignmentProgress.loaded_count} of ${result.consignmentProgress.total_count} pallets for this consignment loaded.`
                    : undefined,
            });
        } catch (scanError) {
            setError((scanError as Error).message);
        } finally {
            setScanning(false);
            busyRef.current = false;
        }
    }

    function scanBarcode(scanBarcode: string) {
        if (!scanBarcode) {
            return;
        }

        void submitScan({
            barcode: scanBarcode,
            clientEventId: crypto.randomUUID(),
            occurredAt: new Date().toISOString(),
            acknowledgedWarnings: [],
        });
    }

    async function simulateScan() {
        if (!manifestId) {
            return;
        }

        setSimulating(true);
        setError('');

        try {
            const response = await fetch(
                `/loading/manifests/${manifestId}/simulate-scan`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );
            const json = await response.json();

            if (!response.ok) {
                throw new Error(
                    json.error?.message ?? 'No simulated pallet is available.',
                );
            }

            setBarcode(json.data.barcode);
            scanBarcode(json.data.barcode);
        } catch (simulationError) {
            setError((simulationError as Error).message);
        } finally {
            setSimulating(false);
        }
    }

    function confirmWarning() {
        if (!warningPrompt) {
            return;
        }

        void submitScan({
            ...warningPrompt,
            acknowledgedWarnings: [
                ...warningPrompt.acknowledgedWarnings,
                warningPrompt.code,
            ],
        });
    }

    const selectedManifest = manifests.find((m) => m.id === manifestId);

    return (
        <AppLayout>
            <Head title="Loading" />

            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Manifest loading</h1>
                    <p className="text-muted-foreground">
                        Select a destination and manifest to begin loading.
                    </p>
                </div>

                <div className="space-y-2">
                    <Label htmlFor="destination">Destination</Label>

                    <Select
                        value={destinationId || undefined}
                        onValueChange={selectDestination}
                    >
                        <SelectTrigger id="destination" className="w-full">
                            <SelectValue placeholder="Select a destination" />
                        </SelectTrigger>
                        <SelectContent>
                            {destinations.map((destination) => (
                                <SelectItem
                                    key={destination.id}
                                    value={destination.id}
                                >
                                    {destination.code} — {destination.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {destinationId && (
                    <div className="space-y-2">
                        <Label htmlFor="manifest">Manifest</Label>

                        {loading && (
                            <p className="text-sm">Loading manifests…</p>
                        )}

                        {error && <AlertError errors={[error]} />}

                        {!loading && !error && manifests.length > 0 && (
                            <Select
                                value={manifestId || undefined}
                                onValueChange={setManifestId}
                            >
                                <SelectTrigger id="manifest" className="w-full">
                                    <SelectValue placeholder="Select a manifest" />
                                </SelectTrigger>
                                <SelectContent>
                                    {manifests.map((manifest) => (
                                        <SelectItem
                                            key={manifest.id}
                                            value={manifest.id}
                                            disabled={
                                                manifest.status !== 'open'
                                            }
                                        >
                                            {manifest.manifest_number} —{' '}
                                            {manifest.service_date} —{' '}
                                            {manifest.status === 'open'
                                                ? `${manifest.manifest_items_count} loaded`
                                                : 'Closed'}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}

                        {!loading && !error && manifests.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No open manifests found for this destination.
                            </p>
                        )}
                    </div>
                )}

                {manifestId && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Scan pallet</CardTitle>
                            <CardDescription>
                                {selectedManifest?.manifest_number} · Loader:{' '}
                                {loader.name}
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <div
                                    className={cn(
                                        'overflow-hidden rounded-md bg-black',
                                        !cameraActive && 'hidden',
                                    )}
                                >
                                    <video
                                        ref={videoRef}
                                        className="aspect-video w-full object-cover"
                                        muted
                                        playsInline
                                    />
                                </div>

                                {isBarcodeDetectionSupported() && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            cameraActive
                                                ? stopCamera()
                                                : void startCamera()
                                        }
                                    >
                                        {cameraActive ? (
                                            <>
                                                <CameraOffIcon /> Stop camera
                                                scan
                                            </>
                                        ) : (
                                            <>
                                                <CameraIcon /> Scan with camera
                                            </>
                                        )}
                                    </Button>
                                )}

                                {cameraError && (
                                    <AlertError errors={[cameraError]} />
                                )}
                            </div>

                            <div className="flex gap-2">
                                <Input
                                    value={barcode}
                                    onChange={(event) =>
                                        setBarcode(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            void scanBarcode(barcode.trim());
                                        }
                                    }}
                                    placeholder="Scan or enter pallet barcode"
                                    autoFocus
                                    disabled={scanning || simulating}
                                />

                                <Button
                                    type="button"
                                    onClick={() =>
                                        void scanBarcode(barcode.trim())
                                    }
                                    disabled={
                                        !barcode.trim() ||
                                        scanning ||
                                        simulating
                                    }
                                >
                                    {scanning ? 'Loading…' : 'Load'}
                                </Button>
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => void simulateScan()}
                                disabled={scanning || simulating}
                            >
                                {simulating
                                    ? 'Simulating scan…'
                                    : 'Simulate seeded scan'}
                            </Button>

                            {error && <AlertError errors={[error]} />}
                        </CardContent>

                        {scanResult && (
                            <CardFooter className="flex-col items-start gap-2 border-t pt-4">
                                <div className="flex items-center gap-2">
                                    <Badge className={LOADED_BADGE_CLASS}>
                                        Loaded
                                    </Badge>
                                    <span className="font-medium">
                                        {scanResult.connoteNumber ??
                                            scanResult.barcode}
                                    </span>
                                    {scanResult.pieceNumber != null && (
                                        <span className="text-sm text-muted-foreground">
                                            piece {scanResult.pieceNumber}
                                        </span>
                                    )}
                                </div>

                                {scanResult.loader && scanResult.scannedAt && (
                                    <p className="text-sm text-muted-foreground">
                                        Scanned by {scanResult.loader} at{' '}
                                        {new Date(
                                            scanResult.scannedAt,
                                        ).toLocaleString()}
                                        .
                                    </p>
                                )}

                                {scanResult.consignmentProgress && (
                                    <p className="text-sm">
                                        {
                                            scanResult.consignmentProgress
                                                .loaded_count
                                        }{' '}
                                        of{' '}
                                        {
                                            scanResult.consignmentProgress
                                                .total_count
                                        }{' '}
                                        pallets loaded for this consignment.
                                    </p>
                                )}

                                {scanResult.progress && (
                                    <p className="text-sm font-medium">
                                        {scanResult.progress.loaded_count} of{' '}
                                        {scanResult.progress.total_count}{' '}
                                        destination pallets loaded.
                                    </p>
                                )}
                            </CardFooter>
                        )}
                    </Card>
                )}

                <Dialog
                    open={warningPrompt !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setWarningPrompt(null);
                        }
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Confirm loading warning</DialogTitle>
                            <DialogDescription>
                                {warningPrompt?.message}
                            </DialogDescription>
                        </DialogHeader>

                        {warningPrompt?.code === 'already_assigned' &&
                            warningPrompt.details && (
                                <dl className="space-y-1 text-sm">
                                    <div>
                                        <dt className="inline font-medium">
                                            Previous manifest:{' '}
                                        </dt>
                                        <dd className="inline">
                                            {String(
                                                warningPrompt.details
                                                    .previous_manifest_number,
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="inline font-medium">
                                            Previous loader:{' '}
                                        </dt>
                                        <dd className="inline">
                                            {String(
                                                warningPrompt.details
                                                    .previous_loader_name,
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="inline font-medium">
                                            Previous scan:{' '}
                                        </dt>
                                        <dd className="inline">
                                            {new Date(
                                                String(
                                                    warningPrompt.details
                                                        .previous_loaded_at,
                                                ),
                                            ).toLocaleString()}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="inline font-medium">
                                            Move to:{' '}
                                        </dt>
                                        <dd className="inline">
                                            {String(
                                                warningPrompt.details
                                                    .selected_manifest_number,
                                            )}
                                        </dd>
                                    </div>
                                </dl>
                            )}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setWarningPrompt(null)}
                                disabled={scanning}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                onClick={confirmWarning}
                                disabled={scanning}
                            >
                                {scanning
                                    ? 'Confirming…'
                                    : warningPrompt?.code === 'already_assigned'
                                      ? 'Override and load here'
                                      : 'Acknowledge and load'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
