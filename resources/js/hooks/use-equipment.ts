import { useCallback, useRef, useState } from 'react';
import { toast } from 'sonner';
import { postJson } from '@/lib/http';
import type { EquipmentCounts } from '@/types/loading';

/**
 * Equipment counts (red/blue pallets and the rest). A tap shows at once and is
 * sent as a relative change, so two loaders tapping together both count.
 */
export function useEquipment(manifestId: string, initial: EquipmentCounts) {
    const [counts, setCounts] = useState(initial);
    const inFlight = useRef(0);

    const adjust = useCallback(
        async (item: string, delta: number) => {
            setCounts((current) => ({
                ...current,
                [item]: Math.max(0, (current[item] ?? 0) + delta),
            }));
            inFlight.current += 1;

            try {
                const json = await postJson<{ data: EquipmentCounts }>(
                    `/loading/manifests/${manifestId}/equipment/adjust`,
                    { item, delta },
                );

                // Only take the server's numbers once every tap has landed, so
                // quick taps do not flicker back.
                if (inFlight.current === 1) {
                    setCounts(json.data);
                }
            } catch (error) {
                setCounts((current) => ({
                    ...current,
                    [item]: Math.max(0, (current[item] ?? 0) - delta),
                }));
                toast.error((error as Error).message);
            } finally {
                inFlight.current -= 1;
            }
        },
        [manifestId],
    );

    return { counts, setCounts, adjust };
}
