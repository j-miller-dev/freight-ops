import { useEffect, useState } from 'react';
import { postJson } from '@/lib/http';

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
export function useManifestPresence(manifestId: string): PresentUser[] {
    const [others, setOthers] = useState<PresentUser[]>([]);

    useEffect(() => {
        let active = true;

        async function beat() {
            if (document.visibilityState !== 'visible' || !navigator.onLine) {
                return;
            }

            try {
                const json = await postJson<{ data: PresentUser[] }>(
                    `/loading/manifests/${manifestId}/presence`,
                );

                if (active) {
                    setOthers(json.data);
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

    return others;
}
