import { Warehouse } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { BayDirection } from '@/hooks/use-unload-runner';

type Props = {
    direction: BayDirection | null;
    onAcknowledge: () => void;
    onCancel: () => void;
};

// Held freight is the exception to "go straight to the destination bay", so the
// runner has to read why before the Confirm button is available.
export default function HoldingDialog({
    direction,
    onAcknowledge,
    onCancel,
}: Props) {
    return (
        <Dialog open={direction !== null}>
            <DialogContent
                showCloseButton={false}
                onEscapeKeyDown={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
            >
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-xl">
                        <Warehouse className="size-6 text-warning" />
                        Hold this freight
                    </DialogTitle>
                    <DialogDescription className="text-base">
                        {direction?.reason}
                    </DialogDescription>
                </DialogHeader>

                {direction && (
                    <div className="space-y-1 rounded-2xl bg-muted p-4 text-center">
                        <p className="text-sm text-muted-foreground">
                            Place it in
                        </p>
                        <p className="text-5xl font-black tracking-wide">
                            {direction.bay.lane
                                ? `Lane ${direction.bay.lane}`
                                : direction.bay.code}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {direction.sender_name}
                            {direction.bay.lane &&
                                ` · lane 1 leaves soonest, lane 5 can wait longest`}
                        </p>
                    </div>
                )}

                <DialogFooter className="gap-2 sm:flex-row">
                    <Button variant="outline" size="xl" onClick={onCancel}>
                        Cancel
                    </Button>
                    <Button
                        size="xl"
                        className="flex-1"
                        onClick={onAcknowledge}
                    >
                        Got it
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
