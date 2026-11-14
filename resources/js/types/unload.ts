export type HoldingLaneStatus = 'overdue' | 'opportunity' | 'settled';

// One active holding lane with freight in it, and whether it still deserves
// to sit. Empty lanes are left out entirely.
export type HoldingLane = {
    id: string;
    code: string;
    lane: number | null;
    pallet_count: number;
    overdue_count: number;
    opportunity_count: number;
    status: HoldingLaneStatus;
    reasons: string[];
};

// One pallet sitting in a holding lane, for a supervisor deciding whether to
// release it early.
export type HoldingLanePallet = {
    id: string;
    barcode: string;
    connote_number: string | null;
    sender_name: string | null;
    status: HoldingLaneStatus;
    reasons: string[];
    released: boolean;
};

// One consignment's progress into its bay, for the runner's guide.
export type ConsignmentPillData = {
    id: string;
    connote_number: string;
    sender_name: string | null;
    item_count: number;
    in_bay: number;
    held: number;
    // Already on a trailer, so no longer in the yard.
    loaded: number;
    // Not in the bay, not held, not loaded: still to be found and placed.
    out_there: number;
    placed_share: number;
};

export type BayBoard = {
    bay: { code: string; name: string } | null;
    current: ConsignmentPillData;
    others: ConsignmentPillData[];
    complete_count: number;
    in_progress_count: number;
};
