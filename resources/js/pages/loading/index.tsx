import { Head, Link } from '@inertiajs/react';
import PageHeader from '@/components/page-header';

type Destination = {
    id: string;
    code: string;
    name: string;
};

type Props = {
    destinations: Destination[];
};

export default function LoadingDestinations({ destinations }: Props) {
    return (
        <>
            <Head title="Load PUD" />

            <PageHeader
                title="Load PUD"
                subtitle="Where is this load going?"
                backHref="/dashboard"
            />

            {destinations.length === 0 ? (
                <p className="py-12 text-center text-muted-foreground">
                    No destinations are available.
                </p>
            ) : (
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    {destinations.map((destination) => (
                        <Link
                            key={destination.id}
                            href={`/loading/depots/${destination.id}`}
                            className="flex aspect-[4/3] flex-col items-center justify-center rounded-2xl border bg-card p-3 text-center shadow-xs transition hover:border-primary hover:shadow-md active:scale-[0.97]"
                        >
                            <span className="text-4xl font-bold tracking-wide text-primary">
                                {destination.code}
                            </span>
                            <span className="mt-1 text-sm text-muted-foreground">
                                {destination.name}
                            </span>
                        </Link>
                    ))}
                </div>
            )}
        </>
    );
}
