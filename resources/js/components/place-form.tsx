import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { FormEventHandler, ReactNode, useEffect, useState } from 'react';

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

// Mirrors the server-side limit, so the form does not offer more rows than can be saved.
const MAX_FILMS = 20;

const describedBy = (helpId: string | null, errorId: string, hasError: boolean) =>
    [helpId, hasError ? errorId : null].filter(Boolean).join(' ') || undefined;

function OptionalTag() {
    return <span className="text-muted-foreground font-normal"> (optional)</span>;
}

export default function PlaceForm({ data, setData, errors, cities, themeOptions, imageUrl, onSubmit, children }: Props) {
    const themesErrorKey = Object.keys(errors).find((key) => key === 'themes' || key.startsWith('themes.'));
    const [selectedImageUrl, setSelectedImageUrl] = useState<string | null>(null);

    // Preview of a newly chosen file; the object URL is released when the file changes.
    useEffect(() => {
        if (!data.image) {
            setSelectedImageUrl(null);

            return;
        }

        const url = URL.createObjectURL(data.image);
        setSelectedImageUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [data.image]);

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
        <form onSubmit={onSubmit} className="mt-6 max-w-2xl space-y-8">
            <p className="text-muted-foreground text-sm">All fields are required unless marked optional.</p>

            <section aria-labelledby="details_heading" className="space-y-6">
                <h2 id="details_heading" className="text-base font-medium">
                    Details
                </h2>

                <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        aria-invalid={errors.name ? true : undefined}
                        aria-describedby={describedBy('name-help', 'name-error', !!errors.name)}
                    />
                    <p id="name-help" className="text-muted-foreground text-sm">
                        At least 3 characters.
                    </p>
                    <InputError id="name-error" message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="city_id">City</Label>
                    <Select value={data.city_id} onValueChange={(value) => setData('city_id', value)}>
                        <SelectTrigger
                            id="city_id"
                            aria-invalid={errors.city_id ? true : undefined}
                            aria-describedby={describedBy(null, 'city_id-error', !!errors.city_id)}
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
                    <InputError id="city_id-error" message={errors.city_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="description">Description</Label>
                    <textarea
                        id="description"
                        className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-32 w-full rounded-md border px-3 py-2 text-base focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden md:text-sm"
                        rows={5}
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        required
                        aria-invalid={errors.description ? true : undefined}
                        aria-describedby={describedBy(null, 'description-error', !!errors.description)}
                    />
                    <InputError id="description-error" message={errors.description} />
                </div>
            </section>

            <fieldset className="min-w-0 space-y-4" aria-describedby="location-help">
                <legend className="text-base font-medium">Location</legend>
                <p id="location-help" className="text-muted-foreground text-sm">
                    Decimal degrees with up to 6 decimal places, for example 52.516275. Use a minus sign for south and west.
                </p>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid min-w-0 content-start gap-2">
                        <Label htmlFor="latitude">Latitude</Label>
                        <Input
                            id="latitude"
                            type="number"
                            step="any"
                            value={data.latitude}
                            onChange={(e) => setData('latitude', e.target.value)}
                            required
                            aria-invalid={errors.latitude ? true : undefined}
                            aria-describedby={describedBy('latitude-help', 'latitude-error', !!errors.latitude)}
                        />
                        <p id="latitude-help" className="text-muted-foreground text-sm">
                            From −90 to 90.
                        </p>
                        <InputError id="latitude-error" message={errors.latitude} />
                    </div>

                    <div className="grid min-w-0 content-start gap-2">
                        <Label htmlFor="longitude">Longitude</Label>
                        <Input
                            id="longitude"
                            type="number"
                            step="any"
                            value={data.longitude}
                            onChange={(e) => setData('longitude', e.target.value)}
                            required
                            aria-invalid={errors.longitude ? true : undefined}
                            aria-describedby={describedBy('longitude-help', 'longitude-error', !!errors.longitude)}
                        />
                        <p id="longitude-help" className="text-muted-foreground text-sm">
                            From −180 to 180.
                        </p>
                        <InputError id="longitude-error" message={errors.longitude} />
                    </div>
                </div>
            </fieldset>

            <fieldset className="min-w-0 space-y-4" aria-describedby="themes-help">
                <legend className="text-base font-medium">
                    Themes
                    <OptionalTag />
                </legend>
                <p id="themes-help" className="text-muted-foreground text-sm">
                    Choose any that describe this place.
                </p>
                <div className="grid gap-3 sm:grid-cols-2">
                    {themeOptions.map((theme) => (
                        <div key={theme.id} className="flex items-center gap-3">
                            <Checkbox
                                id={`theme_${theme.id}`}
                                checked={data.themes.includes(theme.id)}
                                onCheckedChange={(checked) => toggleTheme(theme.id, checked === true)}
                            />
                            <Label htmlFor={`theme_${theme.id}`} className="leading-snug">
                                {theme.label}
                            </Label>
                        </div>
                    ))}
                </div>
                <InputError role="alert" message={themesErrorKey && errors[themesErrorKey]} />
            </fieldset>

            <fieldset className="min-w-0 space-y-4" aria-describedby="films-help">
                <legend className="text-base font-medium">
                    Films &amp; TV
                    <OptionalTag />
                </legend>
                <p id="films-help" className="text-muted-foreground text-sm">
                    Films or series filmed here, one title per row. Up to {MAX_FILMS} titles.
                </p>

                {data.films.length > 0 ? (
                    <ul className="space-y-3">
                        {data.films.map((film, index) => (
                            <li key={index} className="grid gap-2">
                                <div className="flex items-center gap-2">
                                    <Input
                                        className="min-w-0 flex-1"
                                        aria-label={`Film or series title ${index + 1}`}
                                        value={film}
                                        maxLength={255}
                                        onChange={(e) => updateFilm(index, e.target.value)}
                                        aria-invalid={errors[`films.${index}`] ? true : undefined}
                                        aria-describedby={describedBy(null, `films-${index}-error`, !!errors[`films.${index}`])}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="shrink-0"
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
                                <InputError id={`films-${index}-error`} message={errors[`films.${index}`]} />
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-muted-foreground rounded-md border border-dashed px-3 py-6 text-center text-sm">No titles yet.</p>
                )}

                <Button
                    type="button"
                    variant="outline"
                    disabled={data.films.length >= MAX_FILMS}
                    onClick={() => setData('films', [...data.films, ''])}
                >
                    Add title
                </Button>
                <InputError role="alert" message={errors.films} />
            </fieldset>

            <section aria-labelledby="image_heading" className="space-y-4">
                <h2 id="image_heading" className="text-base font-medium">
                    Image
                    <OptionalTag />
                </h2>

                {(imageUrl || selectedImageUrl) && (
                    <div className="flex flex-wrap gap-4">
                        {imageUrl && (
                            <figure className="min-w-0 space-y-2">
                                <img
                                    src={imageUrl}
                                    alt={`Current image of ${data.name}`}
                                    className={`max-h-48 max-w-full rounded-md border object-contain ${selectedImageUrl ? 'opacity-50' : ''}`}
                                />
                                <figcaption className="text-muted-foreground text-sm">
                                    {selectedImageUrl ? 'Current image, replaced on save' : 'Current image'}
                                </figcaption>
                            </figure>
                        )}
                        {selectedImageUrl && (
                            <figure className="min-w-0 space-y-2">
                                <img
                                    src={selectedImageUrl}
                                    alt="Preview of the selected image"
                                    className="max-h-48 max-w-full rounded-md border object-contain"
                                />
                                <figcaption className="text-muted-foreground text-sm">New image</figcaption>
                            </figure>
                        )}
                    </div>
                )}

                <div className="grid gap-2">
                    <Label htmlFor="image">{imageUrl ? 'Upload a new image' : 'Upload an image'}</Label>
                    <Input
                        id="image"
                        type="file"
                        accept="image/jpeg,image/png"
                        aria-invalid={errors.image ? true : undefined}
                        aria-describedby={describedBy('image-help', 'image-error', !!errors.image)}
                        onChange={(e) => setData('image', e.target.files?.[0] ?? null)}
                    />
                    <p id="image-help" className="text-muted-foreground text-sm">
                        JPG or PNG, up to 8 MB and 8 megapixels.{imageUrl && ' Leave empty to keep the current image.'}
                    </p>
                    <InputError id="image-error" message={errors.image} />
                </div>
            </section>

            <div className="flex flex-wrap gap-4 border-t pt-6">{children}</div>
        </form>
    );
}
