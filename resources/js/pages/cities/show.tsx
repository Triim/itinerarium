import { ItineraryCard } from '@/components/itinerary-card';
import { LayerToggles } from '@/components/map/layer-toggles';
import { PlacesMap } from '@/components/map/places-map';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type City, type Itinerary, type Layer, type Place } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Props {
    city: City;
    layers: Layer[];
    places: Place[];
    itineraries: Itinerary[];
}

export default function CityShow({ city, layers, places, itineraries }: Props) {
    const [active, setActive] = useState<number[]>(layers.map((l) => l.id));
    const counts = useMemo(
        () => places.reduce<Record<number, number>>((acc, p) => ({ ...acc, [p.layer_id]: (acc[p.layer_id] ?? 0) + 1 }), {}),
        [places],
    );
    const toggle = (layer: Layer) => setActive((cur) => (cur.includes(layer.id) ? cur.filter((id) => id !== layer.id) : [...cur, layer.id]));

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Города', href: route('cities.index') },
                { title: city.name, href: route('cities.show', city.slug) },
            ]}
        >
            <Head title={city.name} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">{city.name}</h1>
                        {city.description && <p className="text-muted-foreground text-sm">{city.description}</p>}
                    </div>
                    <Button asChild>
                        <Link href={route('itineraries.create', { city: city.slug })}>
                            <Plus /> Составить маршрут
                        </Link>
                    </Button>
                </div>

                <LayerToggles layers={layers} active={active} onToggle={toggle} counts={counts} />
                <PlacesMap center={[city.lat, city.lng]} zoom={city.zoom} places={places} layers={layers} visibleLayerIds={active} />

                <h2 className="mt-4 text-lg font-semibold">Авторские маршруты</h2>
                {itineraries.length === 0 ? (
                    <p className="text-muted-foreground text-sm">Пока нет маршрутов — станьте первым автором.</p>
                ) : (
                    <div className="grid gap-4 md:grid-cols-3">
                        {itineraries.map((it) => (
                            <ItineraryCard key={it.id} itinerary={it} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
