import { useState } from 'react';
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
import { cn } from '@/lib/utils';
import type { LoadCategoryOption, TrailerUnitState } from '@/types/loading';

type Props = {
    unit: TrailerUnitState | null;
    options: LoadCategoryOption[];
    onOpenChange: (open: boolean) => void;
    // Resolves true when saved, so the dialog knows it can close.
    onSave: (unit: number, categories: string[]) => Promise<boolean>;
};

export default function UnitLabelDialog({
    unit,
    options,
    onOpenChange,
    onSave,
}: Props) {
    return (
        <Dialog open={unit !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                {/* Keyed by unit so each opening starts from that unit's labels. */}
                {unit && (
                    <UnitLabelForm
                        key={unit.unit}
                        unit={unit}
                        options={options}
                        onClose={() => onOpenChange(false)}
                        onSave={onSave}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function UnitLabelForm({
    unit,
    options,
    onClose,
    onSave,
}: {
    unit: TrailerUnitState;
    options: LoadCategoryOption[];
    onClose: () => void;
    onSave: Props['onSave'];
}) {
    // Pallets placed on the map show up on the unit too, but only a label is
    // the loader's to change here.
    const [selected, setSelected] = useState<string[]>(() =>
        unit.categories
            .filter((category) => category.source !== 'placed')
            .map((category) => category.key),
    );
    const [saving, setSaving] = useState(false);

    function toggle(key: string) {
        setSelected((current) =>
            current.includes(key)
                ? current.filter((item) => item !== key)
                : [...current, key],
        );
    }

    async function save() {
        setSaving(true);

        if (await onSave(unit.unit, selected)) {
            onClose();
        }

        setSaving(false);
    }

    const groups = [
        { title: 'Common', items: options.filter((o) => o.kind === 'other') },
        {
            title: 'Dangerous goods',
            items: options.filter((o) => o.kind === 'dg'),
        },
    ];

    return (
        <>
            <DialogHeader>
                <DialogTitle className="text-xl">
                    What is on trailer {unit.name}?
                </DialogTitle>
                <DialogDescription>
                    Tap everything this part of the trailer is carrying. The
                    crew sees it, and a clash is flagged if something that must
                    not travel with it is put here.
                </DialogDescription>
            </DialogHeader>

            {groups.map((group) => (
                <section key={group.title} className="space-y-2">
                    <h3 className="text-sm font-medium text-muted-foreground">
                        {group.title}
                    </h3>
                    <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        {group.items.map((option) => {
                            const on = selected.includes(option.key);

                            return (
                                <button
                                    key={option.key}
                                    type="button"
                                    aria-pressed={on}
                                    onClick={() => toggle(option.key)}
                                    className={cn(
                                        'flex h-20 flex-col items-center justify-center gap-1 rounded-xl border text-xs font-medium transition active:scale-[0.97]',
                                        on
                                            ? 'border-primary bg-primary/10 ring-2 ring-primary'
                                            : 'bg-card hover:border-primary',
                                    )}
                                >
                                    <CategoryIcon
                                        category={option.key}
                                        size={32}
                                    />
                                    {option.label}
                                </button>
                            );
                        })}
                    </div>
                </section>
            ))}

            <DialogFooter className="gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className="h-12 rounded-xl"
                    onClick={() => setSelected([])}
                    disabled={saving || selected.length === 0}
                >
                    Clear
                </Button>
                <Button
                    type="button"
                    className="h-12 rounded-xl"
                    onClick={() => void save()}
                    disabled={saving}
                >
                    {saving ? 'Saving…' : 'Save'}
                </Button>
            </DialogFooter>
        </>
    );
}
