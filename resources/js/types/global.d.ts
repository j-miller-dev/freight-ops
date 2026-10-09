import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

// Chrome's Barcode Detection API (not yet in TypeScript's bundled DOM lib).
// https://developer.mozilla.org/en-US/docs/Web/API/Barcode_Detection_API
declare global {
    interface DetectedBarcode {
        readonly rawValue: string;
        readonly format: string;
    }

    class BarcodeDetector {
        constructor(options?: { formats: string[] });
        detect(image: ImageBitmapSource): Promise<DetectedBarcode[]>;
        static getSupportedFormats(): Promise<string[]>;
    }
}
