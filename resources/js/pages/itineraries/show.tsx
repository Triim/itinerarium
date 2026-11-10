import { FlashMessage } from '@/components/flash-message';
import { LayerDot } from '@/components/map/layer-toggles';
import { PlacesMap } from '@/components/map/places-map';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type Itinerary, type Layer } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';

interface Props {
    itinerary: Itinerary & Required<Pick<Itinerary, 'city' | 'places' | 'author'>>;
    layers: Layer[];
    can: { update: boolean };
}

export default function ItineraryShow({ itinerary, layers, can }: Props) {
    const { city, places, author } = itinerary;
    const layerOf = (id: number) => layers.find((l) => l.id === id);

    const destroy = () => {
        if (confirm('Удалить маршрут? Это действие нельзя отменить.')) {
            router.delete(route('itineraries.destroy', itinerary.id));
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Маршруты', href: route('itineraries.index') },
                { title: itinerary.title, href: route('itineraries.show', itinerary.id) },
            ]}
        >
            <Head title={itinerary.title} />
            <div className="flex flex-col gap-4 p-4">
                <FlashMessage />
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-semibold">{itinerary.title}</h1>
                            {!itinerary.is_published && <Badge variant="outline">Черновик</Badge>}
                        </div>
                        <p className="text-muted-foreground text-sm">
                            <Link href={route('cities.show', city.slug)} className="underline-offset-4 hover:underline">
                                {city.name}
                            </Link>{' '}
                            · автор: {author.name} · {places.length} точ.
                        </p>
                        {itinerary.description && <p className="max-w-2xl text-sm">{itinerary.description}</p>}
                    </div>
                    {can.update && (
                        <div className="flex gap-2">
                            <Button variant="outline" asChild>
                                <Link href={route('itineraries.edit', itinerary.id)}>
                                    <Pencil /> Редактировать
                                </Link>
                            </Button>
                            <Button variant="destructive" onClick={destroy}>
                                <Trash2 /> Удалить
                            </Button>
                        </div>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
                    <PlacesMap center={[city.lat, city.lng]} zoom={city.zoom} places={places} layers={layers} path={places} />
                    <ol className="flex flex-col gap-2">
                        {places.map((place, i) => (
                            <li key={place.id} className="border-sidebar-border/70 dark:border-sidebar-border flex gap-3 rounded-lg border p-3">
                                <span className="bg-primary text-primary-foreground flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold">
                                    {i + 1}
                                </span>
                                <div className="space-y-0.5">
                                    <div className="flex items-center gap-2 font-medium">
                                        <LayerDot layer={layerOf(place.layer_id)} />
                                        {place.name}
                                    </div>
                                    {place.description && <p className="text-muted-foreground text-xs">{place.description}</p>}
                                </div>
                            </li>
                        ))}
                    </ol>
                </div>

                {itinerary.selections && itinerary.selections.length > 0 && (
                    <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-sm">
                        В подборках:
                        {itinerary.selections.map((s) => (
                            <Link key={s.id} href={route('selections.show', s.slug)}>
                                <Badge variant="secondary">{s.title}</Badge>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
