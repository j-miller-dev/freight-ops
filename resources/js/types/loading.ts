export type TrailerPosition = {
    unit: number;
    row: number;
    // D = driver side, P = passenger side.
    side: 'D' | 'P';
};

export type DgItem = {
    id: string;
    barcode: string;
    piece_number: number;
    dg_class: string;
    un_number: string | null;
    proper_shipping_name: string | null;
    connote_number: string;
    position: TrailerPosition | null;
};

export type TrailerTypeOption = {
    value: string;
    label: string;
    rows: number[];
    capacity: number;
    confirmed: boolean;
};

export type ManifestSummary = {
    loaded_count: number;
    consignments_total: number;
    consignments_complete: number;
    dg: { class: string; count: number }[];
    dg_items: DgItem[];
    food_count: number;
    food_conflicts: string[];
};

export type ConsignmentState = 'complete' | 'ready' | 'not_ready' | 'elsewhere';

export type ConsignmentRow = {
    id: string;
    connote_number: string;
    sender_name: string | null;
    receiver_name: string | null;
    service_code: string | null;
    item_count: number;
    on_trailer: number;
    on_other_trailers: number;
    in_bay: number;
    not_in_bay: number;
    bay_code: string | null;
    state: ConsignmentState;
    dg_classes: string[];
    has_food: boolean;
};

export type PieceState =
    | 'this_trailer'
    | 'other_trailer'
    | 'in_bay'
    | 'elsewhere'
    | 'not_scanned_in';

export type PieceEvent = {
    type: string;
    occurred_at: string;
    actor: string;
    location_code: string | null;
    manifest_number: string | null;
};

export type Piece = {
    id: string;
    barcode: string;
    piece_number: number;
    weight_kg: number | null;
    dg_class: string | null;
    un_number: string | null;
    proper_shipping_name: string | null;
    is_food: boolean;
    where: { state: PieceState; label: string };
    events: PieceEvent[];
};

export type ConsignmentDetail = {
    id: string;
    connote_number: string;
    sender_name: string | null;
    receiver_name: string | null;
    item_count: number;
    pieces: Piece[];
};
