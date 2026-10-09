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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    connoteNumber: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: (reason: string) => void;
    submitting: boolean;
};

export default function HoldBackDialog({
    connoteNumber,
    open,
    onOpenChange,
    onConfirm,
    submitting,
}: Props) {
    const [reason, setReason] = useState('');

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    setReason('');
                }

                onOpenChange(next);
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="text-xl">Hold back</DialogTitle>
                    <DialogDescription>
                        {connoteNumber} will sit behind other freight on this
                        manifest, even if its service type is urgent. This is
                        recorded against your name.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-2">
                    <Label htmlFor="hold-back-reason">Reason (optional)</Label>
                    <Input
                        id="hold-back-reason"
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        placeholder="e.g. routine store stock, no rush"
                    />
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={submitting}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={() => onConfirm(reason)}
                        disabled={submitting}
                    >
                        Hold back
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
