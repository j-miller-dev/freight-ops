import { PackageMinus, Undo2, X } from 'lucide-react';
import type { ScanOffResult } from '@/hooks/use-manifest-scan-off';
import { cn } from '@/lib/utils';

type Props = {
    result: ScanOffResult | null;
    error: string;
    busy: boolean;
};

// The scan-off counterpart of ScanFeedback. It is deliberately a different
// colour so nobody mistakes "taken off" for "loaded" from arm's length.
export default function ScanOffFeedback({ result, error, busy }: Props) {
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
                    state === 'success' && 'bg-amber-500 text-white',
                    state === 'error' && 'bg-destructive text-white',
                    state === 'idle' && 'bg-muted text-muted-foreground',
                    busy && 'animate-pulse',
                )}
            >
                {state === 'success' && <Undo2 className="size-14" />}
                {state === 'error' && <X className="size-16" strokeWidth={3} />}
                {state === 'idle' && <PackageMinus className="size-14" />}
            </div>

            {state === 'idle' && (
                <p className="text-lg text-muted-foreground">
                    {busy ? 'Taking off…' : 'Scan a pallet to take it off'}
                </p>
            )}

            {state === 'error' && (
                <p className="max-w-sm text-lg font-medium text-destructive">
                    {error}
                </p>
            )}

            {state === 'success' && result && (
                <div className="space-y-1">
                    <p className="text-2xl font-bold text-amber-600 dark:text-amber-400">
                        Taken off
                    </p>
                    <p className="text-lg font-medium">
                        {result.connoteNumber}
                        <span className="font-normal text-muted-foreground">
                            {' '}
                            · piece {result.pieceNumber}
                        </span>
                    </p>
                    <p className="text-muted-foreground">
                        {result.locationCode
                            ? `Back in ${result.locationCode}`
                            : 'Back on the dock, no bay on record'}
                    </p>
                    <p className="text-muted-foreground">
                        {result.consignmentProgress.loaded_count} of{' '}
                        {result.consignmentProgress.total_count} of this
                        consignment still loaded
                    </p>
                </div>
            )}
        </div>
    );
}
