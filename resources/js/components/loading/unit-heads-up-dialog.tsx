import CategoryIcon from '@/components/loading/category-icon';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { UnitNotice } from '@/types/loading';

// Shown to a loader who opens a manifest that already has class 8, 6.1 and
// the like on a unit, so they plan before the first scan, not after a clash.
export default function UnitHeadsUpDialog({
    notices,
    onDismiss,
}: {
    notices: UnitNotice[];
    onDismiss: () => void;
}) {
    return (
        <Dialog
            open={notices.length > 0}
            onOpenChange={(o) => !o && onDismiss()}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="text-xl">
                        Already on this trailer
                    </DialogTitle>
                    <DialogDescription>
                        Plan around these before you load.
                    </DialogDescription>
                </DialogHeader>

                <ul className="space-y-3">
                    {notices.map((unit) => (
                        <li
                            key={unit.unit}
                            className="rounded-2xl border bg-muted/40 p-3"
                        >
                            <p className="mb-2 text-sm font-semibold">
                                Trailer {unit.name}
                            </p>
                            <ul className="space-y-2">
                                {unit.categories.map((category) => (
                                    <li
                                        key={category.key}
                                        className="flex items-center gap-3"
                                    >
                                        <CategoryIcon
                                            category={category.key}
                                            size={36}
                                        />
                                        <span className="min-w-0">
                                            <span className="block font-medium">
                                                {category.label}
                                            </span>
                                            {category.avoid.length > 0 && (
                                                <span className="block text-sm text-muted-foreground">
                                                    Keep{' '}
                                                    {category.avoid.join(
                                                        ' and ',
                                                    )}{' '}
                                                    off {unit.name}
                                                </span>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ul>

                <DialogFooter>
                    <Button size="touch" onClick={onDismiss}>
                        Got it
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
