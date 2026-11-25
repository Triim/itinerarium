import PlaceForm, { type City } from '@/components/place-form';
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
};

type Props = {
    place: Place;
    cities: City[];
};

export default function Edit({ place, cities }: Props) {
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

    const { data, setData, put, processing, errors } = useForm({
        name: place.name,
        description: place.description,
        city_id: String(place.city_id),
        latitude: String(place.latitude),
        longitude: String(place.longitude),
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('userzone.places.update', place.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Place" />
            <div className="p-4">
                <h1 className="text-xl font-semibold">Edit Place</h1>

                <PlaceForm data={data} setData={setData} errors={errors} cities={cities} onSubmit={submit}>
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
