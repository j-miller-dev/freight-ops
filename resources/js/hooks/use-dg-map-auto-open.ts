import { useState } from 'react';

const KEY = 'freight.dgMapAutoOpen';

function read(): boolean {
    try {
        return window.localStorage.getItem(KEY) !== 'off';
    } catch {
        return true;
    }
}

// Whether the trailer map pops up by itself after a DG scan. It is a habit of
// the person holding the device, so it lives in this browser, not on the server.
export function useDgMapAutoOpen(): [boolean, (value: boolean) => void] {
    const [enabled, setEnabled] = useState(read);

    function update(value: boolean) {
        setEnabled(value);

        try {
            window.localStorage.setItem(KEY, value ? 'on' : 'off');
        } catch {
            // Private mode: the choice just lasts until the page reloads.
        }
    }

    return [enabled, update];
}
