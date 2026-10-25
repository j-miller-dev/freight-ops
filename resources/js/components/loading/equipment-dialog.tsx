import Stepper from '@/components/loading/stepper';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { EquipmentCounts, EquipmentItemDef } from '@/types/loading';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    items: EquipmentItemDef[];
    counts: EquipmentCounts;
    onAdjust: (key: string, delta: number) => void;
};

const SWATCH: Record<string, string> = {
    red_pallets: 'bg-red-500',
    blue_pallets: 'bg-blue-500',
};

export default function EquipmentDialog({
    open,
    onOpenChange,
    items,
    counts,
    onAdjust,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="text-xl">Equipment</DialogTitle>
                    <DialogDescription>
                        Count the equipment on the trailer as you go. Each tap
                        is saved straight away.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-3">
                    {items.map((item) => (
                        <Stepper
                            key={item.key}
                            label={item.label}
                            swatch={SWATCH[item.key]}
                            value={counts[item.key] ?? 0}
                            onChange={(delta) => onAdjust(item.key, delta)}
                        />
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}
