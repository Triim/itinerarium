import AppLayout from '@/layouts/app-layout';
import { type City } from '@/types';
import { Head, Link } from '@inertiajs/react';

export default function CitiesIndex({ cities }: { cities: City[] }) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Города', href: route('cities.index') }]}>
            <Head title="Города" />
            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Города</h1>
                    <p className="text-muted-foreground text-sm">Выберите город, чтобы открыть карту со слоями и авторскими маршрутами.</p>
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
                                {city.places_count} точек · {city.itineraries_count} маршрутов
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
