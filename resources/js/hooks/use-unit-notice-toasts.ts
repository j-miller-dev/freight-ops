import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import type { Segregation, UnitNotice } from '@/types/loading';

function keysOf(units: { name: string; categories: { key: string }[] }[]) {
    return units.flatMap((unit) =>
        unit.categories.map((category) => `${unit.name}:${category.key}`),
    );
}

/**
 * Tells this loader when someone else puts something on a unit that others
 * need to plan around ("Class 8 is now on A"). What this loader did themselves
 * is already known from their own summary, so it never toasts back at them.
 * What was there on arrival is the heads-up dialog's job, not a toast.
 */
export function useUnitNoticeToasts(
    notices: UnitNotice[] | null,
    own: Segregation,
) {
    const known = useRef<Set<string> | null>(null);

    // Things this loader can already see for themselves.
    useEffect(() => {
        if (known.current) {
            keysOf(own.units).forEach((key) => known.current?.add(key));
        }
    }, [own]);

    useEffect(() => {
        if (!notices) {
            return;
        }

        if (known.current === null) {
            known.current = new Set(keysOf(notices));

            return;
        }

        const seen = known.current;

        for (const unit of notices) {
            for (const category of unit.categories) {
                if (seen.has(`${unit.name}:${category.key}`)) {
                    continue;
                }

                toast.warning(
                    `${category.label} is now on trailer ${unit.name}`,
                    {
                        description:
                            category.avoid.length > 0
                                ? `Keep ${category.avoid.join(' and ')} off ${unit.name}. Plan around it.`
                                : 'Plan around it.',
                        duration: 10_000,
                    },
                );
            }
        }

        // Replace, so a unit that is emptied and filled again is news again.
        known.current = new Set(keysOf(notices));
    }, [notices]);
}
