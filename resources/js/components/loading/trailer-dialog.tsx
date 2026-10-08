import TrailerPicker from '@/components/loading/trailer-picker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    value: string;
    onOpenChange: (open: boolean) => void;
    onChange: (value: string) => void;
};

export default function TrailerDialog({
    open,
    value,
    onOpenChange,
    onChange,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="text-xl">Trailer type</DialogTitle>
                    <DialogDescription>
                        Choose the trailer this manifest is loading onto.
                    </DialogDescription>
                </DialogHeader>

                <TrailerPicker
                    value={value}
                    onChange={(next) => {
                        onChange(next);
                        onOpenChange(false);
                    }}
                />
            </DialogContent>
        </Dialog>
    );
}
