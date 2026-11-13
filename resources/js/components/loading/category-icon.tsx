import { Apple, Droplet, Flame } from 'lucide-react';
import DgDiamond from '@/components/loading/dg-diamond';

// One picture per kind of freight: the DG diamond for a class, and an icon for
// the things that are not a DG class.
export default function CategoryIcon({
    category,
    size = 28,
}: {
    category: string;
    size?: number;
}) {
    if (category === 'food') {
        return (
            <Apple
                style={{ width: size, height: size }}
                className="text-success"
            />
        );
    }

    if (category === 'oil') {
        return (
            <Droplet
                style={{ width: size, height: size }}
                className="text-amber-600"
            />
        );
    }

    if (category === 'flammable') {
        return (
            <Flame
                style={{ width: size, height: size }}
                className="text-destructive"
            />
        );
    }

    return <DgDiamond cls={category} size={size} />;
}
