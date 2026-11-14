import ConsignmentPill from '@/components/unload/consignment-pill';
import type { BayBoard } from '@/types/unload';

// The other orders for this bay, closest to complete first. A rough guide to
// which consignments are worth finishing so each ends up in one bay.
export default function BayGuide({ board }: { board: BayBoard | null }) {
    if (!board || !board.bay) {
        return null;
    }

    if (board.others.length === 0 && board.complete_count === 0) {
        return null;
    }

    const bayCode = board.bay.code;

    return (
        <section
            aria-label={`Other orders for ${bayCode}`}
            className="mx-auto mt-4 w-full max-w-xl space-y-3 rounded-3xl border bg-card p-5 shadow-xs"
        >
            <div>
                <h2 className="text-lg font-bold">Also in {bayCode}</h2>
                <p className="text-sm text-muted-foreground">
                    Finish the fullest pills first.
                </p>
            </div>

            {board.others.length > 0 && (
                <ul className="flex flex-wrap gap-2">
                    {board.others.map((pill) => (
                        <ConsignmentPill
                            key={pill.id}
                            pill={pill}
                            bayCode={bayCode}
                        />
                    ))}
                </ul>
            )}

            {board.complete_count > 0 && (
                <p className="text-sm font-medium text-success">
                    {board.complete_count}{' '}
                    {board.complete_count === 1 ? 'order' : 'orders'} complete
                    in {bayCode}
                </p>
            )}
        </section>
    );
}
