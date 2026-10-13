import { Map as MapIcon, Maximize2, Palette, Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

function Planned({ icon, children }: { icon: ReactNode; children: ReactNode }) {
    return (
        <li className="flex items-start gap-3">
            <span className="mt-0.5 text-primary">{icon}</span>
            <span>{children}</span>
        </li>
    );
}

// Placeholder: new starters carry a paper map of the depot, so this will become
// a tap-to-zoom, colour-coded floor plan. Needs the real floor plan first.
export default function WarehouseMapDialog() {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    size="lg"
                    className="gap-2"
                    aria-label="Warehouse map"
                >
                    <MapIcon />
                    <span className="hidden sm:inline">Map</span>
                </Button>
            </DialogTrigger>

            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-xl">
                        <MapIcon className="size-6 text-primary" />
                        Warehouse map
                    </DialogTitle>
                    <DialogDescription className="text-base">
                        Coming soon: no more paper maps.
                    </DialogDescription>
                </DialogHeader>

                <div
                    className="flex aspect-[4/3] items-center justify-center rounded-2xl border-2 border-dashed bg-muted text-muted-foreground"
                    role="img"
                    aria-label="Map placeholder"
                >
                    <MapIcon className="size-16" strokeWidth={1.25} />
                </div>

                <ul className="space-y-2 text-sm">
                    <Planned icon={<Palette className="size-5" />}>
                        Colour-coded areas: bays, holding lanes, docks and
                        walkways.
                    </Planned>
                    <Planned icon={<Maximize2 className="size-5" />}>
                        Pinch or tap to zoom into any area.
                    </Planned>
                    <Planned icon={<Search className="size-5" />}>
                        Tap an area to see what it is, or jump to the bay for
                        the pallet you just scanned.
                    </Planned>
                </ul>
            </DialogContent>
        </Dialog>
    );
}
