import { useCallback, useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';
import {
    BarcodeDetectionScanner,
    isBarcodeDetectionSupported,
} from '@/lib/barcode-scanner';

export function useBarcodeCamera(
    videoRef: RefObject<HTMLVideoElement | null>,
    onScan: (value: string) => void,
) {
    const scannerRef = useRef<BarcodeDetectionScanner | null>(null);
    const onScanRef = useRef(onScan);
    const [active, setActive] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        onScanRef.current = onScan;
    });

    const stop = useCallback(() => {
        scannerRef.current?.stop();
        scannerRef.current = null;
        setActive(false);
    }, []);

    const start = useCallback(async () => {
        if (!videoRef.current) {
            return;
        }

        setError('');

        const scanner = new BarcodeDetectionScanner();
        scannerRef.current = scanner;
        scanner.onScan((value) => onScanRef.current(value));

        try {
            await scanner.start(videoRef.current);
            setActive(true);
        } catch {
            setError(
                'Camera access was denied or is unavailable. Use manual entry.',
            );
            stop();
        }
    }, [stop, videoRef]);

    // Leaving the page must release the camera.
    useEffect(() => {
        return () => {
            scannerRef.current?.stop();
            scannerRef.current = null;
        };
    }, []);

    return {
        active,
        error,
        start,
        stop,
        supported: isBarcodeDetectionSupported(),
    };
}
