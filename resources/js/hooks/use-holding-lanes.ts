import { useEffect, useState } from 'react';
import { getJson } from '@/lib/http';
import type { HoldingLane } from '@/types/unload';

const POLL_MS = 30_000;

/** Holding lanes with freight in them, polled so a runner doesn't have to remember to check. */
export function useHoldingLanes() {
    const [lanes, setLanes] = useState<HoldingLane[]>([]);

    useEffect(() => {
        let active = true;

        function load() {
            getJson<{ data: HoldingLane[] }>('/unload/holding-lanes')
                .then((json) => active && setLanes(json.data))
                .catch(() => undefined);
        }

        load();
        const id = setInterval(load, POLL_MS);

        return () => {
            active = false;
            clearInterval(id);
        };
    }, []);

    return lanes;
}
