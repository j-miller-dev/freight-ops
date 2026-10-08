export type TrailerType = {
    value: string;
    label: string;
    // Pallet positions, when known.
    capacity: number | null;
};

// Mirrors the "Configuration" column on the linehaul planning sheet. A
// B-double is 7 rows of 2 on the lead trailer plus 10 rows of 2 on the rear;
// the other layouts are still to be confirmed. Each type will later carry its
// own block layout so dangerous goods can be assigned to specific positions.
export const TRAILER_TYPES: readonly TrailerType[] = [
    { value: 'b_double', label: 'B-Double', capacity: 34 },
    { value: 'b_triple', label: 'B-Triple', capacity: null },
    { value: 'a_double', label: 'A-Double', capacity: null },
];

export const DEFAULT_TRAILER_TYPE = 'b_double';

export function trailerType(value: string): TrailerType {
    return (
        TRAILER_TYPES.find((type) => type.value === value) ?? TRAILER_TYPES[0]
    );
}
