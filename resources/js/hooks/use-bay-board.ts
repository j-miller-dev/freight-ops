import { useEffect, useState } from 'react';
import { getJson } from '@/lib/http';
import type { BayBoard } from '@/types/unload';

/**
 * How much of a consignment, and of the other orders for its bay, is in place.
 * Refetched when the consignment changes or `refreshKey` does (a drop was
 * confirmed). Offline or on failure it is simply absent: the runner screen
 * still shows its own counts.
 */
export function useBayBoard(
    consignmentId: string | null,
    refreshKey: unknown,
): BayBoard | null {
    const [board, setBoard] = useState<BayBoard | null>(null);

    useEffect(() => {
        if (!consignmentId) {
            return;
        }

        let active = true;

        getJson<{ data: BayBoard }>(
            `/unload/consignments/${consignmentId}/bay-board`,
        )
            .then((json) => active && setBoard(json.data))
            .catch(() => undefined);

        return () => {
            active = false;
        };
    }, [consignmentId, refreshKey]);

    // Never show the previous consignment's board while the new one loads.
    return board && board.current.id === consignmentId ? board : null;
}
