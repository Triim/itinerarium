import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { FormEventHandler, ReactNode } from 'react';

export type City = {
    id: number;
    name: string;
};

type PlaceFormData = {
    name: string;
    description: string;
    city_id: string;
    latitude: string;
    longitude: string;
    image: File | null;
};

type Props = {
    data: PlaceFormData;
    setData: (field: keyof PlaceFormData, value: string | File | null) => void;
    errors: Partial<Record<keyof PlaceFormData, string>>;
    cities: City[];
    imageUrl?: string | null;
    onSubmit: FormEventHandler;
    children: ReactNode;
};

export default function PlaceForm({ data, setData, errors, cities, imageUrl, onSubmit, children }: Props) {
    return (
        <form onSubmit={onSubmit} className="mt-4 max-w-xl space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>
                <textarea
                    id="description"
                    className="border-input focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                    rows={5}
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    required
                />
                <InputError message={errors.description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="city_id">City</Label>
                <Select value={data.city_id} onValueChange={(value) => setData('city_id', value)}>
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

            <div className="grid gap-2">
                <Label htmlFor="latitude">Latitude</Label>
                <Input
                    id="latitude"
                    type="number"
                    step="any"
                    value={data.latitude}
                    onChange={(e) => setData('latitude', e.target.value)}
                    required
                />
                <InputError message={errors.latitude} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="longitude">Longitude</Label>
                <Input
                    id="longitude"
                    type="number"
                    step="any"
                    value={data.longitude}
                    onChange={(e) => setData('longitude', e.target.value)}
                    required
                />
                <InputError message={errors.longitude} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="image">Image (optional)</Label>
                {imageUrl && (
                    <img src={imageUrl} alt={`Current image of ${data.name}`} className="max-h-48 max-w-full justify-self-start rounded-md" />
                )}
                <Input
                    id="image"
                    type="file"
                    accept="image/jpeg,image/png"
                    aria-describedby="image-help"
                    onChange={(e) => setData('image', e.target.files?.[0] ?? null)}
                />
                <p id="image-help" className="text-muted-foreground text-sm">
                    JPG or PNG, up to 8 MB.{imageUrl && ' Leave empty to keep the current image.'}
                </p>
                <InputError message={errors.image} />
            </div>

            {children}
        </form>
    );
}
