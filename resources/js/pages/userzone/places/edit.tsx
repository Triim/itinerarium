import PlaceForm, { type City, type PlaceFormData, type ThemeOption } from '@/components/place-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Place = {
    id: number;
    name: string;
    description: string;
    city_id: number;
    latitude: number;
    longitude: number;
    themes: string[];
    films: string[];
};

type Props = {
    place: Place;
    cities: City[];
    image_url: string | null;
    theme_options: ThemeOption[];
};

export default function Edit({ place, cities, image_url, theme_options }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Places',
            href: '/user/places',
        },
        {
            title: 'Edit Place',
            href: `/user/places/${place.id}/edit`,
        },
    ];

    const { data, setData, post, transform, processing, errors } = useForm<PlaceFormData>({
        name: place.name,
        description: place.description,
        city_id: String(place.city_id),
        latitude: String(place.latitude),
        longitude: String(place.longitude),
        themes: place.themes,
        films: place.films,
        image: null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // PHP does not read files from a multipart PUT request, so the form is
        // sent as a POST request with a spoofed PUT method.
        transform((data) => ({ ...data, _method: 'PUT' }));
        post(route('userzone.places.update', place.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Place" />
            <div className="p-4">
                <h1 className="text-xl font-semibold">Edit Place</h1>

                <PlaceForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    cities={cities}
                    themeOptions={theme_options}
                    imageUrl={image_url}
                    onSubmit={submit}
                >
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving…' : 'Save Place'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('userzone.places.index')}>Cancel</Link>
                    </Button>
                </PlaceForm>
            </div>
        </AppLayout>
    );
}
