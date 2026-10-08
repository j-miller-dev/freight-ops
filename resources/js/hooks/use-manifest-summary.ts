import { useEffect, useState } from 'react';
import { getJson } from '@/lib/http';
import type { ManifestSummary } from '@/types/loading';

/**
 * What is on the trailer. Refetched whenever `trigger` changes (a completed
 * scan); offline or on failure the last known summary stays on screen.
 */
export function useManifestSummary(
    manifestId: string,
    initial: ManifestSummary,
    trigger: unknown,
): ManifestSummary {
    const [summary, setSummary] = useState(initial);

    useEffect(() => {
        if (!trigger) {
            return;
        }

        let active = true;

        getJson<{ data: ManifestSummary }>(
            `/loading/manifests/${manifestId}/summary`,
        )
            .then((json) => active && setSummary(json.data))
            .catch(() => undefined);

        return () => {
            active = false;
        };
    }, [manifestId, trigger]);

    return summary;
}
