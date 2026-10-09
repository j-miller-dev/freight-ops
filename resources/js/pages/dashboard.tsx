import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, PackageOpen, Truck } from 'lucide-react';

export default function Dashboard() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Home" />

            <div className="mb-8">
                <p className="text-muted-foreground">Signed in as</p>
                <h1 className="text-3xl font-bold tracking-tight">
                    {auth.user.name}
                </h1>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <Link
                    href="/loading"
                    className="group flex min-h-48 flex-col justify-between rounded-3xl bg-primary p-6 text-primary-foreground shadow-sm transition-transform active:scale-[0.98]"
                >
                    <Truck className="size-14" strokeWidth={1.5} />
                    <div className="flex items-end justify-between">
                        <div>
                            <p className="text-3xl font-bold">Load PUD</p>
                            <p className="opacity-80">
                                Scan pallets onto a manifest
                            </p>
                        </div>
                        <ArrowRight className="size-8 transition-transform group-hover:translate-x-1" />
                    </div>
                </Link>

                <div
                    aria-disabled="true"
                    className="flex min-h-48 flex-col justify-between rounded-3xl border border-dashed p-6 text-muted-foreground"
                >
                    <PackageOpen className="size-14" strokeWidth={1.5} />
                    <div>
                        <p className="text-3xl font-bold">Unload</p>
                        <p>Coming soon</p>
                    </div>
                </div>
            </div>
        </>
    );
}
