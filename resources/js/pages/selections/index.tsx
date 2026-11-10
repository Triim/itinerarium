import AppLayout from '@/layouts/app-layout';
import { type Selection } from '@/types';
import { Head, Link } from '@inertiajs/react';

export default function SelectionsIndex({ selections }: { selections: Selection[] }) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Collections', href: route('selections.index') }]}>
            <Head title="Collections" />
            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Route collections</h1>
                    <p className="text-muted-foreground text-sm">Editorial and author collections of routes, grouped by theme.</p>
                </div>
                <div className="grid gap-4 md:grid-cols-3">
                    {selections.map((s) => (
                        <Link
                            key={s.id}
                            href={route('selections.show', s.slug)}
                            className="border-sidebar-border/70 dark:border-sidebar-border hover:bg-accent/50 flex flex-col gap-2 rounded-xl border p-5 transition-colors"
                        >
                            <h2 className="text-lg font-semibold">{s.title}</h2>
                            {s.description && <p className="text-muted-foreground text-sm">{s.description}</p>}
                            <div className="text-muted-foreground mt-auto text-xs">
                                {s.city ? s.city.name : 'Various cities'} · {s.itineraries_count} routes · {s.author?.name}
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
