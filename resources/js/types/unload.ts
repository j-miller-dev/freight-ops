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
