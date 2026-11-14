import { useEffect, useState } from 'react';
import { postJson } from '@/lib/http';
import type { UnitNotice } from '@/types/loading';

export type PresentUser = {
    id: number;
    name: string;
    loaded_count: number;
};

// How often the open page tells the server it is still here, and asks who
// else is. Must stay well under the server's 45 second "recently seen" window.
const HEARTBEAT_MS = 15_000;

/**
 * Who else has this manifest open right now. There is no websocket service in
 * production, so this is a heartbeat: each tick records that we are here and
 * returns the other people seen recently. It pauses while the tab is hidden
 * or offline, and keeps the last known list if a tick fails.
 */
export function useManifestPresence(manifestId: string): {
    others: PresentUser[];
    // What the crew should plan around, as of the latest heartbeat. Null until
    // the first answer arrives.
    notices: UnitNotice[] | null;
    // The same list as it stood when this page first heard back: the heads-up
    // for someone who has just opened the manifest.
    entryNotices: UnitNotice[] | null;
} {
    const [others, setOthers] = useState<PresentUser[]>([]);
    const [notices, setNotices] = useState<UnitNotice[] | null>(null);
    const [entryNotices, setEntryNotices] = useState<UnitNotice[] | null>(null);

    useEffect(() => {
        let active = true;

        async function beat() {
            if (document.visibilityState !== 'visible' || !navigator.onLine) {
                return;
            }

            try {
                const json = await postJson<{
                    data: PresentUser[];
                    unit_notices?: UnitNotice[];
                }>(`/loading/manifests/${manifestId}/presence`);

                if (active) {
                    const next = json.unit_notices ?? [];

                    setOthers(json.data);
                    setNotices(next);
                    setEntryNotices((first) => first ?? next);
                }
            } catch {
                // Presence is a nicety; keep whatever we last knew.
            }
        }

        void beat();
        const id = setInterval(() => void beat(), HEARTBEAT_MS);
        const onVisible = () => void beat();
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            active = false;
            clearInterval(id);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [manifestId]);

    return { others, notices, entryNotices };
}
