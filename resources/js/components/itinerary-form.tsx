import InputError from '@/components/input-error';
import { type City } from '@/components/place-form';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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

    // Laravel reports errors for individual places as "place_ids.0", "place_ids.1", ...
    const placesErrorKey = Object.keys(errors).find((key) => key === 'place_ids' || key.startsWith('place_ids.'));

    const selectCity = (cityId: string) => {
        setData((data) => ({
            ...data,
            city_id: cityId,
            place_ids: places.filter((place) => String(place.city_id) === cityId && data.place_ids.includes(place.id)).map((place) => place.id),
        }));
    };

    const togglePlace = (placeId: number, checked: boolean) => {
        setData((data) => ({
            ...data,
            place_ids: checked ? [...data.place_ids, placeId] : data.place_ids.filter((id) => id !== placeId),
        }));
    };

    return (
        <form onSubmit={onSubmit} className="mt-4 max-w-xl space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="title">Title</Label>
                <Input id="title" value={data.title} onChange={(e) => setData((data) => ({ ...data, title: e.target.value }))} required />
                <InputError message={errors.title} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="city_id">City</Label>
                <Select value={data.city_id} onValueChange={selectCity}>
                    <SelectTrigger id="city_id">
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
                <InputError message={errors.city_id} />
            </div>

            <fieldset className="grid gap-3">
                <legend className="mb-3 text-sm leading-none font-medium">Places</legend>
                {cityPlaces.map((place) => (
                    <div key={place.id} className="flex items-center gap-3">
                        <Checkbox
                            id={`place_${place.id}`}
                            checked={data.place_ids.includes(place.id)}
                            onCheckedChange={(checked) => togglePlace(place.id, checked === true)}
                        />
                        <Label htmlFor={`place_${place.id}`}>{place.name}</Label>
                    </div>
                ))}
                {cityPlaces.length === 0 && (
                    <p className="text-muted-foreground text-sm">{data.city_id ? 'No places in this city yet.' : 'Select a city to see its places.'}</p>
                )}
                <InputError message={placesErrorKey && errors[placesErrorKey]} />
            </fieldset>

            {children}
        </form>
    );
}
