import { Menu } from 'lucide-react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import ConsignmentPill from '@/components/unload/consignment-pill';
import type { BayBoard } from '@/types/unload';

// The other orders for this bay, closest to complete first. Tucked into a side
// drawer so it stays out of the way until a runner wants to pick a target.
export default function BayGuide({ board }: { board: BayBoard | null }) {
    if (!board || !board.bay) {
        return null;
    }

    if (board.others.length === 0 && board.complete_count === 0) {
        return null;
    }

    const bayCode = board.bay.code;

    return (
        <Sheet>
            <SheetTrigger asChild>
                <button
                    type="button"
                    aria-label={`Other orders for ${bayCode}`}
                    className="fixed top-1/2 right-0 z-40 flex -translate-y-1/2 flex-col items-center gap-1 rounded-l-2xl border border-r-0 bg-card px-2.5 py-3 shadow-md active:bg-muted"
                >
                    <Menu className="size-6" />
                    <span className="text-xs font-bold tabular-nums">
                        {board.others.length}
                    </span>
                </button>
            </SheetTrigger>

            <SheetContent side="right" className="w-full max-w-sm gap-0">
                <SheetHeader className="border-b">
                    <SheetTitle className="text-xl">
                        Also in {bayCode}
                    </SheetTitle>
                    <SheetDescription>
                        Fullest first. Finish these so each order ends up in one
                        bay.
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 space-y-3 overflow-y-auto p-4">
                    {board.others.length > 0 && (
                        <ul className="space-y-2">
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
                            {board.complete_count === 1 ? 'order' : 'orders'}{' '}
                            complete in {bayCode}
                        </p>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
