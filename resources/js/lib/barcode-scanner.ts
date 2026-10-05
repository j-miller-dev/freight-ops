export interface BarcodeScanner {
    start(video: HTMLVideoElement): Promise<void>;
    stop(): void;
    onScan(callback: (value: string) => void): void;
}

export function isBarcodeDetectionSupported(): boolean {
    return typeof window !== 'undefined' && 'BarcodeDetector' in window;
}

const DEFAULT_FORMATS = ['code_128', 'code_39', 'ean_13', 'qr_code'];

// Repeat detections of the same code within this window are treated as one
// scan, since the camera keeps seeing the same barcode across frames.
const REPEAT_SCAN_DEBOUNCE_MS = 2000;

export class BarcodeDetectionScanner implements BarcodeScanner {
    private readonly detector: BarcodeDetector;
    private stream: MediaStream | null = null;
    private video: HTMLVideoElement | null = null;
    private frameRequest: number | null = null;
    private callback: ((value: string) => void) | null = null;
    private lastValue: string | null = null;
    private lastScannedAt = 0;

    constructor(formats: string[] = DEFAULT_FORMATS) {
        this.detector = new BarcodeDetector({ formats });
    }

    async start(video: HTMLVideoElement): Promise<void> {
        this.stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
        });

        this.video = video;
        video.srcObject = this.stream;
        await video.play();

        this.scheduleNextFrame();
    }

    stop(): void {
        if (this.frameRequest !== null) {
            cancelAnimationFrame(this.frameRequest);
            this.frameRequest = null;
        }

        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;

        if (this.video) {
            this.video.srcObject = null;
            this.video = null;
        }
    }

    onScan(callback: (value: string) => void): void {
        this.callback = callback;
    }

    private scheduleNextFrame(): void {
        this.frameRequest = requestAnimationFrame(() => void this.tick());
    }

    private async tick(): Promise<void> {
        if (!this.video) {
            return;
        }

        try {
            const [barcode] = await this.detector.detect(this.video);

            if (barcode) {
                this.handleDetection(barcode.rawValue);
            }
        } catch {
            // Transient decode errors are expected between frames; keep scanning.
        }

        this.scheduleNextFrame();
    }

    private handleDetection(value: string): void {
        const now = Date.now();
        const isRepeat =
            value === this.lastValue &&
            now - this.lastScannedAt < REPEAT_SCAN_DEBOUNCE_MS;

        if (isRepeat) {
            return;
        }

        this.lastValue = value;
        this.lastScannedAt = now;
        this.callback?.(value);
    }
}
