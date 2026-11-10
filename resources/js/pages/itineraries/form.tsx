import InputError from '@/components/input-error';
import { LayerDot, LayerToggles } from '@/components/map/layer-toggles';
import { PlacesMap } from '@/components/map/places-map';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type City, type Itinerary, type Layer, type Place } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, X } from 'lucide-react';
import { type FormEventHandler, useMemo, useState } from 'react';

interface Props {
    itinerary: (Itinerary & { places: Pick<Place, 'id'>[] }) | null;
    initialCityId: number | null;
    cities: (City & { places: Place[] })[];
    layers: Layer[];
}

export default function ItineraryForm({ itinerary, initialCityId, cities, layers }: Props) {
    const isEdit = itinerary !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        title: itinerary?.title ?? '',
        description: itinerary?.description ?? '',
        city_id: initialCityId ?? cities[0]?.id ?? 0,
        is_published: itinerary?.is_published ?? false,
        place_ids: itinerary?.places.map((p) => p.id) ?? ([] as number[]),
    });

    const [activeLayers, setActiveLayers] = useState<number[]>(layers.map((l) => l.id));

    const city = cities.find((c) => c.id === data.city_id) ?? cities[0];
    const placesById = useMemo(() => new Map(city.places.map((p) => [p.id, p])), [city]);
    const stops = data.place_ids.map((id) => placesById.get(id)).filter((p): p is Place => p !== undefined);
    const layerOf = (id: number) => layers.find((l) => l.id === id);

    const changeCity = (value: string) => {
        if (data.place_ids.length > 0 && !confirm('Changing the city will clear the route stops. Continue?')) return;
        setData((d) => ({ ...d, city_id: Number(value), place_ids: [] }));
    };

    const toggleStop = (id: number) =>
        setData('place_ids', data.place_ids.includes(id) ? data.place_ids.filter((x) => x !== id) : [...data.place_ids, id]);

    const move = (index: number, delta: number) => {
        const next = [...data.place_ids];
        const target = index + delta;
        if (target < 0 || target >= next.length) return;
        [next[index], next[target]] = [next[target], next[index]];
        setData('place_ids', next);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit) put(route('itineraries.update', itinerary.id));
        else post(route('itineraries.store'));
    };

    const stopErrors = Object.entries(errors)
        .filter(([key]) => key.startsWith('place_ids'))
        .map(([, msg]) => msg);

    const title = isEdit ? 'Edit route' : 'New route';

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'My routes', href: route('dashboard') },
                { title, href: '#' },
            ]}
        >
            <Head title={title} />
            <form onSubmit={submit} className="grid gap-6 p-4 lg:grid-cols-[360px_1fr]">
                <div className="flex flex-col gap-5">
                    <h1 className="text-2xl font-semibold">{title}</h1>

                    <div className="grid gap-2">
                        <Label htmlFor="title">Title</Label>
                        <Input
                            id="title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            required
                            placeholder="e.g. Petersburg in one day"
                        />
                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            rows={4}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Who is this route for, how long does it take…"
                            className="border-input placeholder:text-muted-foreground focus-visible:ring-ring/50 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label>City</Label>
                        <Select value={String(data.city_id)} onValueChange={changeCity}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {cities.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.city_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Stops ({stops.length})</Label>
                        {stops.length === 0 && <p className="text-muted-foreground text-sm">Click a point on the map and choose “Add to route”.</p>}
                        <ol className="flex flex-col gap-1">
                            {stops.map((place, i) => (
                                <li
                                    key={place.id}
                                    className="border-sidebar-border/70 dark:border-sidebar-border flex items-center gap-2 rounded-md border px-2 py-1.5 text-sm"
                                >
                                    <span className="text-muted-foreground w-5 text-right text-xs">{i + 1}.</span>
                                    <LayerDot layer={layerOf(place.layer_id)} />
                                    <span className="flex-1 truncate">{place.name}</span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        onClick={() => move(i, -1)}
                                        disabled={i === 0}
                                        aria-label="Move up"
                                    >
                                        <ArrowUp />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        onClick={() => move(i, 1)}
                                        disabled={i === stops.length - 1}
                                        aria-label="Move down"
                                    >
                                        <ArrowDown />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        onClick={() => toggleStop(place.id)}
                                        aria-label="Remove"
                                    >
                                        <X />
                                    </Button>
                                </li>
                            ))}
                        </ol>
                        {stopErrors.map((msg, i) => (
                            <InputError key={i} message={msg} />
                        ))}
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox id="is_published" checked={data.is_published} onCheckedChange={(v) => setData('is_published', v === true)} />
                        <Label htmlFor="is_published">Publish (visible to everyone)</Label>
                    </div>

                    <Button type="submit" disabled={processing}>
                        {isEdit ? 'Save' : 'Create route'}
                    </Button>
                </div>

                <div className="flex flex-col gap-3">
                    <LayerToggles
                        layers={layers}
                        active={activeLayers}
                        onToggle={(l) => setActiveLayers((cur) => (cur.includes(l.id) ? cur.filter((x) => x !== l.id) : [...cur, l.id]))}
                    />
                    <PlacesMap
                        key={city.id}
                        center={[city.lat, city.lng]}
                        zoom={city.zoom}
                        places={city.places}
                        layers={layers}
                        visibleLayerIds={activeLayers}
                        path={stops}
                        highlightedIds={data.place_ids}
                        className="h-[600px] w-full rounded-xl"
                        renderPopup={(place) => (
                            <Button
                                type="button"
                                size="sm"
                                className="mt-2"
                                variant={data.place_ids.includes(place.id) ? 'outline' : 'default'}
                                onClick={() => toggleStop(place.id)}
                            >
                                {data.place_ids.includes(place.id) ? 'Remove from route' : 'Add to route'}
                            </Button>
                        )}
                    />
                </div>
            </form>
        </AppLayout>
    );
}
