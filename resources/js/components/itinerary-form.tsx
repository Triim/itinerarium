import InputError from '@/components/input-error';
import { type City } from '@/components/place-form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowDown, ArrowUp, X } from 'lucide-react';
import { FormEventHandler, ReactNode } from 'react';

export type Place = {
    id: number;
    name: string;
    city_id: number;
};

export type ItineraryFormData = {
    title: string;
    city_id: string;
    place_ids: number[];
};

type Props = {
    data: ItineraryFormData;
    setData: (update: (data: ItineraryFormData) => ItineraryFormData) => void;
    errors: Partial<Record<string, string>>;
    cities: City[];
    places: Place[];
    onSubmit: FormEventHandler;
    children: ReactNode;
};

export default function ItineraryForm({ data, setData, errors, cities, places, onSubmit, children }: Props) {
    const cityPlaces = places.filter((place) => String(place.city_id) === data.city_id);
    const placesById = new Map(places.map((place) => [place.id, place]));
    // place_ids is the stop order.
    const stops = data.place_ids.map((id) => placesById.get(id)).filter((place) => place !== undefined);

    // Laravel reports errors for individual places as "place_ids.0", "place_ids.1", ...
    const placesErrorKey = Object.keys(errors).find((key) => key === 'place_ids' || key.startsWith('place_ids.'));

    const selectCity = (cityId: string) => {
        setData((data) => ({
            ...data,
            city_id: cityId,
            place_ids: data.place_ids.filter((id) => String(placesById.get(id)?.city_id) === cityId),
        }));
    };

    const togglePlace = (placeId: number, checked: boolean) => {
        setData((data) => ({
            ...data,
            place_ids: checked ? [...data.place_ids, placeId] : data.place_ids.filter((id) => id !== placeId),
        }));
    };

    const moveStop = (placeId: number, offset: -1 | 1) => {
        setData((data) => {
            const placeIds = [...data.place_ids];
            const from = placeIds.indexOf(placeId);
            const to = from + offset;

            if (from === -1 || to < 0 || to >= placeIds.length) {
                return data;
            }

            [placeIds[from], placeIds[to]] = [placeIds[to], placeIds[from]];

            return { ...data, place_ids: placeIds };
        });
    };

    return (
        <form onSubmit={onSubmit} className="mt-6 max-w-4xl space-y-8">
            <section aria-labelledby="details_heading" className="max-w-xl space-y-6">
                <h2 id="details_heading" className="text-base font-medium">
                    Details
                </h2>

                <div className="grid gap-2">
                    <Label htmlFor="title">Title</Label>
                    <Input
                        id="title"
                        value={data.title}
                        onChange={(e) => setData((data) => ({ ...data, title: e.target.value }))}
                        required
                        aria-invalid={errors.title ? true : undefined}
                        aria-describedby={errors.title ? 'title-error' : undefined}
                    />
                    <InputError id="title-error" message={errors.title} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="city_id">City</Label>
                    <Select value={data.city_id} onValueChange={selectCity}>
                        <SelectTrigger
                            id="city_id"
                            aria-invalid={errors.city_id ? true : undefined}
                            aria-describedby={errors.city_id ? 'city_id-help city_id-error' : 'city_id-help'}
                        >
                            <SelectValue placeholder="Select a city" />
                        </SelectTrigger>
                        <SelectContent>
                            {cities.map((city) => (
                                <SelectItem key={city.id} value={String(city.id)}>
                                    {city.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <p id="city_id-help" className="text-muted-foreground text-sm">
                        All stops must be in this city. Changing it removes stops from the previous city.
                    </p>
                    <InputError id="city_id-error" message={errors.city_id} />
                </div>
            </section>

            <section aria-labelledby="stops_section_heading" className="space-y-4">
                <div className="space-y-1">
                    <h2 id="stops_section_heading" className="text-base font-medium">
                        Stops
                    </h2>
                    <p className="text-muted-foreground text-sm">Tick the places to visit, then put them in order.</p>
                </div>

                <InputError role="alert" message={placesErrorKey && errors[placesErrorKey]} />

                <div className="grid gap-6 md:grid-cols-2">
                    <fieldset className="min-w-0 space-y-3">
                        <legend className="mb-3 text-sm leading-none font-medium">
                            Places in this city
                            {cityPlaces.length > 0 && (
                                <span className="text-muted-foreground font-normal">
                                    {' '}
                                    · {stops.length} of {cityPlaces.length} selected
                                </span>
                            )}
                        </legend>
                        {cityPlaces.length > 0 ? (
                            <ul className="max-h-96 divide-y overflow-y-auto rounded-md border">
                                {cityPlaces.map((place) => {
                                    const stopIndex = data.place_ids.indexOf(place.id);

                                    return (
                                        <li key={place.id} className="flex items-center gap-3 px-3 py-2.5">
                                            <Checkbox
                                                id={`place_${place.id}`}
                                                checked={stopIndex !== -1}
                                                onCheckedChange={(checked) => togglePlace(place.id, checked === true)}
                                            />
                                            <Label htmlFor={`place_${place.id}`} className="min-w-0 flex-1 leading-snug break-words">
                                                {place.name}
                                            </Label>
                                            {stopIndex !== -1 && (
                                                <span className="text-muted-foreground shrink-0 text-xs tabular-nums">Stop {stopIndex + 1}</span>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground rounded-md border border-dashed px-3 py-6 text-center text-sm">
                                {data.city_id ? 'No places in this city yet.' : 'Select a city to see its places.'}
                            </p>
                        )}
                    </fieldset>

                    <section aria-labelledby="stops_heading" className="min-w-0 space-y-3">
                        <h3 id="stops_heading" className="text-sm leading-none font-medium">
                            Stop order
                        </h3>
                        {stops.length > 0 ? (
                            <ol className="divide-y rounded-md border">
                                {stops.map((place, index) => (
                                    <li key={place.id} className="flex items-center gap-2 px-3 py-2">
                                        <span className="text-muted-foreground w-6 shrink-0 text-sm tabular-nums">{index + 1}.</span>
                                        <span className="min-w-0 flex-1 text-sm break-words">{place.name}</span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="size-9 shrink-0"
                                            aria-label={`Move ${place.name} up`}
                                            title="Move up"
                                            disabled={index === 0}
                                            onClick={() => moveStop(place.id, -1)}
                                        >
                                            <ArrowUp aria-hidden="true" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="size-9 shrink-0"
                                            aria-label={`Move ${place.name} down`}
                                            title="Move down"
                                            disabled={index === stops.length - 1}
                                            onClick={() => moveStop(place.id, 1)}
                                        >
                                            <ArrowDown aria-hidden="true" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-9 shrink-0"
                                            aria-label={`Remove ${place.name}`}
                                            title="Remove"
                                            onClick={() => togglePlace(place.id, false)}
                                        >
                                            <X aria-hidden="true" />
                                        </Button>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <p className="text-muted-foreground rounded-md border border-dashed px-3 py-6 text-center text-sm">
                                No stops yet. Selected places appear here in visiting order.
                            </p>
                        )}
                    </section>
                </div>
            </section>

            <div className="flex flex-wrap gap-4 border-t pt-6">{children}</div>
        </form>
    );
}
