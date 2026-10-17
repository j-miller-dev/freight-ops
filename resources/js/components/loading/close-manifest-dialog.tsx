import { TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    manifestNumber: string;
    consignmentsTotal: number;
    consignmentsComplete: number;
    loadingFinished: boolean;
    // Resolves true when the manifest was closed.
    onClose: () => Promise<boolean>;
};

export default function CloseManifestDialog({
    open,
    onOpenChange,
    manifestNumber,
    consignmentsTotal,
    consignmentsComplete,
    loadingFinished,
    onClose,
}: Props) {
    const [busy, setBusy] = useState(false);
    const outstanding = consignmentsTotal - consignmentsComplete;
    const ready = outstanding === 0 && loadingFinished;

    async function submit() {
        setBusy(true);
        const closed = await onClose();
        setBusy(false);

        if (closed) {
            onOpenChange(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="text-xl">
                        Close manifest {manifestNumber}
                    </DialogTitle>
                    <DialogDescription>
                        This stops further scans and marks the manifest
                        dispatched. It can't be reopened from here.
                    </DialogDescription>
                </DialogHeader>

                <dl className="space-y-2 text-sm">
                    <div className="flex justify-between gap-4 border-b py-2">
                        <dt className="text-muted-foreground">
                            Consignments complete
                        </dt>
                        <dd className="font-medium">
                            {consignmentsComplete} / {consignmentsTotal}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-4 py-2">
                        <dt className="text-muted-foreground">
                            Loader finished
                        </dt>
                        <dd className="font-medium">
                            {loadingFinished ? 'Yes' : 'Not yet'}
                        </dd>
                    </div>
                </dl>

                {!ready && (
                    <p className="flex items-center gap-2 rounded-xl bg-warning px-3 py-2 text-sm font-semibold text-warning-foreground">
                        <TriangleAlert className="size-5 shrink-0" />
                        {outstanding > 0
                            ? `${outstanding} consignment${outstanding === 1 ? '' : 's'} still outstanding. `
                            : ''}
                        Closing now will record this as an override.
                    </p>
                )}

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={busy}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant={ready ? 'success' : 'warning'}
                        onClick={() => void submit()}
                        disabled={busy}
                    >
                        {busy && <Spinner />}
                        {ready ? 'Close manifest' : 'Close anyway'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
