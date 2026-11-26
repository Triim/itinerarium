import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { FormEventHandler, ReactNode } from 'react';

export type City = {
    id: number;
    name: string;
};

export type ThemeOption = {
    id: string;
    label: string;
};

export type PlaceFormData = {
    name: string;
    description: string;
    city_id: string;
    latitude: string;
    longitude: string;
    themes: string[];
    films: string[];
    image: File | null;
};

type Props = {
    data: PlaceFormData;
    setData: <K extends keyof PlaceFormData>(field: K, value: PlaceFormData[K]) => void;
    // Laravel reports errors for list items as "films.0", "films.1", ...
    errors: Partial<Record<string, string>>;
    cities: City[];
    themeOptions: ThemeOption[];
    imageUrl?: string | null;
    onSubmit: FormEventHandler;
    children: ReactNode;
};

export default function PlaceForm({ data, setData, errors, cities, themeOptions, imageUrl, onSubmit, children }: Props) {
    const themesErrorKey = Object.keys(errors).find((key) => key === 'themes' || key.startsWith('themes.'));

    const toggleTheme = (themeId: string, checked: boolean) => {
        setData('themes', checked ? [...data.themes, themeId] : data.themes.filter((id) => id !== themeId));
    };

    const updateFilm = (index: number, title: string) => {
        setData(
            'films',
            data.films.map((film, i) => (i === index ? title : film)),
        );
    };

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
                <Input id="latitude" type="number" step="any" value={data.latitude} onChange={(e) => setData('latitude', e.target.value)} required />
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

            <fieldset className="grid gap-3">
                <legend className="mb-3 text-sm leading-none font-medium">Themes (optional)</legend>
                {themeOptions.map((theme) => (
                    <div key={theme.id} className="flex items-center gap-3">
                        <Checkbox
                            id={`theme_${theme.id}`}
                            checked={data.themes.includes(theme.id)}
                            onCheckedChange={(checked) => toggleTheme(theme.id, checked === true)}
                        />
                        <Label htmlFor={`theme_${theme.id}`}>{theme.label}</Label>
                    </div>
                ))}
                <InputError message={themesErrorKey && errors[themesErrorKey]} />
            </fieldset>

            <fieldset className="grid gap-3">
                <legend className="mb-3 text-sm leading-none font-medium">Films &amp; TV (optional)</legend>
                {data.films.map((film, index) => (
                    <div key={index} className="grid gap-2">
                        <div className="flex items-center gap-2">
                            <Input
                                aria-label={`Film or series title ${index + 1}`}
                                value={film}
                                maxLength={255}
                                onChange={(e) => updateFilm(index, e.target.value)}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                aria-label={`Remove film or series title ${index + 1}`}
                                onClick={() =>
                                    setData(
                                        'films',
                                        data.films.filter((_, i) => i !== index),
                                    )
                                }
                            >
                                Remove
                            </Button>
                        </div>
                        <InputError message={errors[`films.${index}`]} />
                    </div>
                ))}
                {data.films.length === 0 && <p className="text-muted-foreground text-sm">No film or series titles yet.</p>}
                <Button type="button" variant="outline" className="justify-self-start" onClick={() => setData('films', [...data.films, ''])}>
                    Add title
                </Button>
                <InputError message={errors.films} />
            </fieldset>

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
