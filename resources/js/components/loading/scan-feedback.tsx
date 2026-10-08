import { Check, ScanBarcode, X } from 'lucide-react';
import type { ScanResult } from '@/hooks/use-manifest-scanner';
import { cn } from '@/lib/utils';

type Props = {
    result: ScanResult | null;
    error: string;
    scanning: boolean;
};

// One big, unmistakable status circle: loaders glance at this from arm's
// length, so colour and icon carry the message before any text is read.
export default function ScanFeedback({ result, error, scanning }: Props) {
    const state = error ? 'error' : result ? 'success' : 'idle';

    return (
        <div
            role="status"
            aria-live="polite"
            className="flex flex-col items-center gap-3 text-center"
        >
            <div
                className={cn(
                    'flex size-28 items-center justify-center rounded-full transition-colors',
                    state === 'success' && 'bg-success text-success-foreground',
                    state === 'error' && 'bg-destructive text-white',
                    state === 'idle' && 'bg-muted text-muted-foreground',
                    scanning && 'animate-pulse',
                )}
            >
                {state === 'success' && (
                    <Check className="size-16" strokeWidth={3} />
                )}
                {state === 'error' && <X className="size-16" strokeWidth={3} />}
                {state === 'idle' && <ScanBarcode className="size-14" />}
            </div>

            {state === 'idle' && (
                <p className="text-lg text-muted-foreground">
                    {scanning ? 'Loading…' : 'Ready to scan'}
                </p>
            )}

            {state === 'error' && (
                <p className="max-w-sm text-lg font-medium text-destructive">
                    {error}
                </p>
            )}

            {state === 'success' && result && (
                <div className="space-y-1">
                    <p className="text-2xl font-bold text-success">
                        Item loaded
                    </p>
                    <p className="text-lg font-medium">
                        {result.connoteNumber ?? result.barcode}
                        {result.pieceNumber != null && (
                            <span className="font-normal text-muted-foreground">
                                {' '}
                                · piece {result.pieceNumber}
                            </span>
                        )}
                    </p>
                    {result.consignmentProgress && (
                        <p className="text-muted-foreground">
                            {result.consignmentProgress.loaded_count} of{' '}
                            {result.consignmentProgress.total_count} for this
                            consignment
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
