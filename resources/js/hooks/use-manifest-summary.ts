import { useCallback, useEffect, useState } from 'react';
import { getJson } from '@/lib/http';
import type { ManifestSummary } from '@/types/loading';

/**
 * What is on the trailer. Refetched whenever `trigger` changes (a completed
 * scan) or on demand with `refresh`; offline or on failure the last known
 * summary stays on screen.
 */
export function useManifestSummary(
    manifestId: string,
    initial: ManifestSummary,
    trigger: unknown,
) {
    const [summary, setSummary] = useState(initial);
    const url = `/loading/manifests/${manifestId}/summary`;

    useEffect(() => {
        if (!trigger) {
            return;
        }

        let active = true;

        getJson<{ data: ManifestSummary }>(url)
            .then((json) => active && setSummary(json.data))
            .catch(() => undefined);

        return () => {
            active = false;
        };
    }, [url, trigger]);

    const refresh = useCallback(async () => {
        try {
            setSummary((await getJson<{ data: ManifestSummary }>(url)).data);
        } catch {
            // Keep the last known summary.
        }
    }, [url]);

    return { summary, refresh };
}
