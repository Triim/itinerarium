import AppLayout from '@/layouts/app-layout';
import { type City } from '@/types';
import { Head, Link } from '@inertiajs/react';

export default function CitiesIndex({ cities }: { cities: City[] }) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Cities', href: route('cities.index') }]}>
            <Head title="Cities" />
            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Cities</h1>
                    <p className="text-muted-foreground text-sm">Pick a city to open its layered map and author routes.</p>
                </div>
                <div className="grid gap-4 md:grid-cols-3">
                    {cities.map((city) => (
                        <Link
                            key={city.id}
                            href={route('cities.show', city.slug)}
                            className="border-sidebar-border/70 dark:border-sidebar-border hover:bg-accent/50 flex flex-col gap-2 rounded-xl border p-5 transition-colors"
                        >
                            <div className="text-muted-foreground text-xs tracking-wide uppercase">{city.country}</div>
                            <h2 className="text-lg font-semibold">{city.name}</h2>
                            {city.description && <p className="text-muted-foreground text-sm">{city.description}</p>}
                            <div className="text-muted-foreground mt-auto text-xs">
                                {city.places_count} places · {city.itineraries_count} routes
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
