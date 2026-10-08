import type { TrailerPosition } from '@/types/loading';

export function unitName(unitCount: number, index: number): string {
    if (unitCount === 1) {
        return 'Trailer';
    }

    if (index === 0) {
        return 'Lead trailer';
    }

    if (index === unitCount - 1) {
        return 'Rear trailer';
    }

    return `Trailer ${index + 1}`;
}

export function sideName(side: TrailerPosition['side']): string {
    return side === 'D' ? 'Driver side' : 'Passenger side';
}

export function positionLabel(
    unitCount: number,
    position: TrailerPosition,
): string {
    return `${unitName(unitCount, position.unit - 1)} · row ${position.row} · ${sideName(position.side)}`;
}
