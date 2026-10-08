export type TrailerType = {
    value: string;
    label: string;
};

// Mirrors the "Configuration" column on the linehaul planning sheet. Each type
// will later carry its own block layout so dangerous goods can be assigned to
// specific positions on the trailer.
export const TRAILER_TYPES: readonly TrailerType[] = [
    { value: 'b-double', label: 'B-Double' },
    { value: 'b-triple', label: 'B-Triple' },
    { value: 'a-double', label: 'A-Double' },
];

export const DEFAULT_TRAILER_TYPE = 'b-double';
