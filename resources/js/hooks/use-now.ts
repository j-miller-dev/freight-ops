import { useSyncExternalStore } from 'react';

const STEP_MS = 30_000;

function subscribe(onChange: () => void) {
    const id = setInterval(onChange, 15_000);

    return () => clearInterval(id);
}

// Wall-clock time, quantised so the snapshot is stable between ticks.
export function useNow(): number {
    return useSyncExternalStore(
        subscribe,
        () => Math.floor(Date.now() / STEP_MS) * STEP_MS,
        () => 0,
    );
}
