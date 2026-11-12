import { Users } from 'lucide-react';
import { useInitials } from '@/hooks/use-initials';
import type { PresentUser } from '@/hooks/use-manifest-presence';

// "Sam is also on this trailer": so two people loading the same manifest can
// see each other before they both reach for the same pallet.
export default function PresenceChips({ others }: { others: PresentUser[] }) {
    const getInitials = useInitials();

    if (others.length === 0) {
        return null;
    }

    return (
        <section
            aria-label="Others loading this manifest"
            className="flex flex-wrap items-center gap-3 rounded-2xl bg-primary/10 px-4 py-3"
        >
            <Users className="size-5 shrink-0 text-primary" />
            <p className="text-sm font-medium">
                {others.length === 1
                    ? 'Also loading this trailer:'
                    : `${others.length} others loading this trailer:`}
            </p>
            <ul className="flex flex-wrap gap-2">
                {others.map((person) => (
                    <li
                        key={person.id}
                        className="flex items-center gap-2 rounded-full bg-card py-1 pr-3 pl-1 text-sm shadow-xs"
                    >
                        <span
                            aria-hidden
                            className="flex size-7 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
                        >
                            {getInitials(person.name)}
                        </span>
                        <span className="font-medium">{person.name}</span>
                        <span className="text-muted-foreground tabular-nums">
                            {person.loaded_count} loaded
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
