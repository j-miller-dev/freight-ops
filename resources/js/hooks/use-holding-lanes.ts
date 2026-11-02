import { useCallback, useEffect, useRef, useState } from 'react';
import { getJson } from '@/lib/http';
import type { HoldingLane } from '@/types/unload';

const POLL_MS = 30_000;

/** Holding lanes with freight in them, polled so a runner doesn't have to remember to check. */
export function useHoldingLanes() {
    const [lanes, setLanes] = useState<HoldingLane[]>([]);
    const active = useRef(true);

    useEffect(() => {
        active.current = true;

        function load() {
            getJson<{ data: HoldingLane[] }>('/unload/holding-lanes')
                .then((json) => active.current && setLanes(json.data))
                .catch(() => undefined);
        }

        load();
        const id = setInterval(load, POLL_MS);

        return () => {
            active.current = false;
            clearInterval(id);
        };
    }, []);

    const refresh = useCallback(() => {
        getJson<{ data: HoldingLane[] }>('/unload/holding-lanes')
            .then((json) => active.current && setLanes(json.data))
            .catch(() => undefined);
    }, []);

    return { lanes, refresh };
}
