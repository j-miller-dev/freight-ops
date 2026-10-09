import { TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import Stepper from '@/components/loading/stepper';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import type { EquipmentCounts, EquipmentItemDef } from '@/types/loading';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    trailerName: string;
    items: EquipmentItemDef[];
    counts: EquipmentCounts;
    clash: boolean;
    // Resolves true when the trailer was marked finished.
    onFinish: (counts: EquipmentCounts) => Promise<boolean>;
};

const SWATCH: Record<string, string> = {
    red_pallets: 'bg-red-500',
    blue_pallets: 'bg-blue-500',
};

function FinishBody({
    trailerName,
    items,
    counts,
    clash,
    onFinish,
}: Omit<Props, 'open' | 'onOpenChange'> & { onDone: () => void }) {
    // Starts from what has been counted so far and is only sent on Finish.
    const [draft, setDraft] = useState(counts);
    const [busy, setBusy] = useState(false);

    function change(key: string, delta: number) {
        setDraft((current) => ({
            ...current,
            [key]: Math.max(0, (current[key] ?? 0) + delta),
        }));
    }

    async function submit() {
        setBusy(true);
        await onFinish(draft);
        setBusy(false);
    }

    return (
        <>
            <DialogHeader>
                <DialogTitle className="text-xl">
                    Finish loading {trailerName}
                </DialogTitle>
                <DialogDescription>
                    Count what went on the trailer. The scaler is told as soon
                    as you finish.
                </DialogDescription>
            </DialogHeader>

            {clash && (
                <p className="flex items-center gap-2 rounded-xl bg-destructive px-3 py-2 text-sm font-semibold text-white">
                    <TriangleAlert className="size-5 shrink-0" />
                    Food is still on this trailer with class 6.1 or 8.
                </p>
            )}

            <div className="grid gap-2 sm:grid-cols-2">
                {items.map((item) => (
                    <Stepper
                        key={item.key}
                        label={item.label}
                        value={draft[item.key] ?? 0}
                        swatch={SWATCH[item.key]}
                        disabled={busy}
                        onChange={(delta) => change(item.key, delta)}
                    />
                ))}
            </div>

            <Button
                size="xl"
                variant="success"
                className="w-full"
                disabled={busy}
                onClick={() => void submit()}
            >
                {busy && <Spinner />}
                Finish loading
            </Button>
        </>
    );
}

export default function FinishDialog({ open, onOpenChange, ...rest }: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
                <FinishBody {...rest} onDone={() => onOpenChange(false)} />
            </DialogContent>
        </Dialog>
    );
}
