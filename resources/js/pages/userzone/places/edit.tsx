import PlaceForm, { type City, type ThemeOption } from '@/components/place-form';
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

    const { data, setData, post, put, transform, processing, errors } = useForm({
        name: place.name,
        description: place.description,
        city_id: String(place.city_id),
        latitude: String(place.latitude),
        longitude: String(place.longitude),
        themes: place.themes,
        films: place.films,
        image: null as File | null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const url = route('userzone.places.update', place.id);

        // PHP does not read files from a multipart PUT request, so an image is
        // sent as a POST request with a spoofed method.
        transform((data) => (data.image ? { ...data, _method: 'PUT' } : data));

        if (data.image) {
            post(url);
        } else {
            put(url);
        }
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
                    <div className="flex gap-4">
                        <Button type="submit" disabled={processing}>
                            Update Place
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={route('userzone.places.index')}>Cancel</Link>
                        </Button>
                    </div>
                </PlaceForm>
            </div>
        </AppLayout>
    );
}
