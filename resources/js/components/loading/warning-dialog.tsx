import { TriangleAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { WarningPrompt } from '@/hooks/use-manifest-scanner';

type Props = {
    warning: WarningPrompt | null;
    busy: boolean;
    onConfirm: () => void;
    onCancel: () => void;
};

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-4 border-b py-2 last:border-0">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{value}</dd>
        </div>
    );
}

export default function WarningDialog({
    warning,
    busy,
    onConfirm,
    onCancel,
}: Props) {
    const details = warning?.details;

    return (
        <Dialog
            open={warning !== null}
            onOpenChange={(open) => !open && onCancel()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-xl">
                        <TriangleAlert className="size-6 text-warning" />
                        Check before loading
                    </DialogTitle>
                    <DialogDescription className="text-base">
                        {warning?.message}
                    </DialogDescription>
                </DialogHeader>

                {warning?.code === 'handling_unit_already_assigned' &&
                    details && (
                        <dl className="text-sm">
                            <Row
                                label="Previous manifest"
                                value={String(details.previous_manifest_number)}
                            />
                            <Row
                                label="Previous loader"
                                value={String(details.previous_loader_name)}
                            />
                            <Row
                                label="Previous scan"
                                value={new Date(
                                    String(details.previous_loaded_at),
                                ).toLocaleString()}
                            />
                            <Row
                                label="Move to"
                                value={String(details.selected_manifest_number)}
                            />
                        </dl>
                    )}

                {warning?.code === 'consignment_split' && details && (
                    <div className="space-y-3 text-sm">
                        <dl>
                            {(
                                details.conflicts as Array<{
                                    manifest_number: string;
                                    pallet_count: number;
                                }>
                            ).map((conflict) => (
                                <Row
                                    key={conflict.manifest_number}
                                    label={conflict.manifest_number}
                                    value={`${conflict.pallet_count} pallets`}
                                />
                            ))}
                        </dl>
                        <p className="text-muted-foreground">
                            {String(details.on_selected_after_scan)} of{' '}
                            {String(details.total_count)} pallets will be on
                            this manifest after loading.
                        </p>
                    </div>
                )}

                {warning?.code === 'destination_mismatch' && details && (
                    <dl className="text-sm">
                        <Row
                            label="Pallet destination"
                            value={`${String(details.destination_code)}${
                                details.destination_name
                                    ? ` — ${String(details.destination_name)}`
                                    : ''
                            }`}
                        />
                        <Row
                            label="This manifest"
                            value={String(details.manifest_number)}
                        />
                    </dl>
                )}

                <DialogFooter className="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="touch"
                        onClick={onCancel}
                        disabled={busy}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="warning"
                        size="touch"
                        onClick={onConfirm}
                        disabled={busy}
                    >
                        {busy
                            ? 'Confirming…'
                            : warning?.code === 'handling_unit_already_assigned'
                              ? 'Move and load here'
                              : 'Acknowledge and load'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
