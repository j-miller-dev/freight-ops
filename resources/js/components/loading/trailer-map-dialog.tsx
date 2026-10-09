import { Check } from 'lucide-react';
import { useState } from 'react';
import DgDiamond from '@/components/loading/dg-diamond';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { positionLabel, sideName, unitName } from '@/lib/trailer-types';
import { cn } from '@/lib/utils';
import type {
    DgItem,
    TrailerPosition,
    TrailerTypeOption,
} from '@/types/loading';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    trailer: TrailerTypeOption;
    items: DgItem[];
    active: DgItem | null;
    onActiveChange: (id: string) => void;
    // Resolves true when the position was saved.
    onPlace: (
        item: DgItem,
        position: TrailerPosition | null,
    ) => Promise<boolean>;
    // The pallet that was just scanned, when the dialog opened because of it.
    justLoaded: DgItem | null;
};

const SIDES: TrailerPosition['side'][] = ['P', 'D'];

function samePosition(a: TrailerPosition | null, b: TrailerPosition): boolean {
    return (
        a !== null && a.unit === b.unit && a.row === b.row && a.side === b.side
    );
}

export default function TrailerMapDialog({
    open,
    onOpenChange,
    trailer,
    items,
    active,
    onActiveChange,
    onPlace,
    justLoaded,
}: Props) {
    const [saving, setSaving] = useState(false);
    const unitCount = trailer.rows.length;

    async function save(item: DgItem, position: TrailerPosition | null) {
        setSaving(true);
        const saved = await onPlace(item, position);
        setSaving(false);

        // Placing the pallet that was just scanned finishes the job.
        if (saved && position && justLoaded && item.id === justLoaded.id) {
            onOpenChange(false);
        }
    }

    function tapCell(position: TrailerPosition) {
        const occupant = items.find((item) =>
            samePosition(item.position, position),
        );

        if (occupant) {
            onActiveChange(occupant.id);

            return;
        }

        if (active) {
            void save(active, position);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    {justLoaded ? (
                        <div className="flex items-center gap-4">
                            <span className="flex size-14 shrink-0 items-center justify-center rounded-full bg-success text-success-foreground">
                                <Check className="size-9" strokeWidth={3} />
                            </span>
                            <div className="min-w-0 text-left">
                                <DialogTitle className="text-2xl">
                                    Dangerous goods loaded
                                </DialogTitle>
                                <DialogDescription className="text-base">
                                    {justLoaded.connote_number} · piece{' '}
                                    {justLoaded.piece_number}
                                    {' · '}Tap where it sits on the trailer.
                                </DialogDescription>
                            </div>
                            <DgDiamond cls={justLoaded.dg_class} size={64} />
                        </div>
                    ) : (
                        <>
                            <DialogTitle className="text-xl">
                                Dangerous goods on this trailer
                            </DialogTitle>
                            <DialogDescription>
                                Select an item, then tap its position.
                            </DialogDescription>
                        </>
                    )}
                </DialogHeader>

                <div className="grid gap-6 md:grid-cols-[auto_1fr]">
                    <div>
                        <p className="mb-2 text-center text-xs font-medium text-muted-foreground">
                            ▲ Front of vehicle
                        </p>

                        <div className="flex flex-wrap items-start justify-center gap-3">
                            {trailer.rows.map((rowCount, unitIndex) => (
                                <div
                                    key={unitIndex}
                                    className="rounded-xl border bg-muted/40 p-2"
                                >
                                    <p className="mb-1 text-center text-xs font-semibold">
                                        {unitName(unitCount, unitIndex)}
                                    </p>

                                    <div className="grid grid-cols-[1.25rem_3.5rem_3.5rem] items-center gap-1">
                                        <span />
                                        {SIDES.map((side) => (
                                            <span
                                                key={side}
                                                className="text-center text-[10px] font-medium text-muted-foreground uppercase"
                                            >
                                                {side === 'D'
                                                    ? 'Driver'
                                                    : 'Pass.'}
                                            </span>
                                        ))}

                                        {Array.from(
                                            { length: rowCount },
                                            (_, i) => i + 1,
                                        ).map((row) => (
                                            <RowCells
                                                key={row}
                                                row={row}
                                                unit={unitIndex + 1}
                                                items={items}
                                                active={active}
                                                disabled={saving || !active}
                                                onTap={tapCell}
                                            />
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>

                        {!trailer.confirmed && (
                            <p className="mt-2 text-center text-xs text-muted-foreground">
                                {trailer.label} layout is approximate until
                                confirmed.
                            </p>
                        )}
                    </div>

                    <div className="space-y-3">
                        {active && (
                            <div className="rounded-2xl border-2 border-primary bg-primary/5 p-3">
                                <div className="flex items-center gap-3">
                                    <DgDiamond
                                        cls={active.dg_class}
                                        size={48}
                                    />
                                    <div className="min-w-0">
                                        <p className="font-semibold">
                                            Class {active.dg_class}
                                            {active.un_number &&
                                                ` · ${active.un_number}`}
                                        </p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {active.proper_shipping_name}
                                        </p>
                                        <p className="font-mono text-xs">
                                            {active.barcode}
                                        </p>
                                    </div>
                                </div>

                                <p className="mt-2 text-sm font-medium">
                                    {active.position
                                        ? positionLabel(
                                              unitCount,
                                              active.position,
                                          )
                                        : 'Not placed yet'}
                                </p>

                                {active.position && (
                                    <Button
                                        variant="outline"
                                        className="mt-2 h-10 rounded-xl"
                                        disabled={saving}
                                        onClick={() => void save(active, null)}
                                    >
                                        Clear position
                                    </Button>
                                )}
                            </div>
                        )}

                        {items.length > 1 && (
                            <ul className="max-h-60 space-y-1.5 overflow-y-auto">
                                {items.map((item) => (
                                    <li key={item.id}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                onActiveChange(item.id)
                                            }
                                            aria-pressed={
                                                item.id === active?.id
                                            }
                                            className={cn(
                                                'flex w-full items-center gap-3 rounded-xl border px-3 py-2 text-left',
                                                item.id === active?.id &&
                                                    'border-primary bg-primary/5',
                                            )}
                                        >
                                            <DgDiamond
                                                cls={item.dg_class}
                                                size={30}
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-mono text-xs">
                                                    {item.barcode}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'block text-xs',
                                                        item.position
                                                            ? 'text-muted-foreground'
                                                            : 'font-semibold text-warning-foreground',
                                                    )}
                                                >
                                                    {item.position
                                                        ? `${unitName(unitCount, item.position.unit - 1)} · row ${item.position.row} · ${sideName(item.position.side)}`
                                                        : 'Not placed'}
                                                </span>
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        size="touch"
                        variant={justLoaded ? 'outline' : 'default'}
                        onClick={() => onOpenChange(false)}
                    >
                        {justLoaded ? 'Skip for now' : 'Done'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function RowCells({
    row,
    unit,
    items,
    active,
    disabled,
    onTap,
}: {
    row: number;
    unit: number;
    items: DgItem[];
    active: DgItem | null;
    disabled: boolean;
    onTap: (position: TrailerPosition) => void;
}) {
    return (
        <>
            <span className="text-right text-[10px] text-muted-foreground tabular-nums">
                {row}
            </span>

            {SIDES.map((side) => {
                const position: TrailerPosition = { unit, row, side };
                const occupant = items.find((item) =>
                    samePosition(item.position, position),
                );
                const isActive =
                    occupant !== undefined && occupant.id === active?.id;

                return (
                    <button
                        key={side}
                        type="button"
                        disabled={disabled && !occupant}
                        onClick={() => onTap(position)}
                        aria-label={`Row ${row}, ${sideName(side)}${occupant ? `, class ${occupant.dg_class}` : ''}`}
                        className={cn(
                            'flex h-9 items-center justify-center rounded-md border text-xs transition-colors',
                            occupant
                                ? 'bg-card'
                                : 'bg-card/60 hover:border-primary hover:bg-primary/10 disabled:opacity-50',
                            isActive && 'ring-2 ring-primary',
                        )}
                    >
                        {occupant && (
                            <DgDiamond cls={occupant.dg_class} size={26} />
                        )}
                    </button>
                );
            })}
        </>
    );
}
