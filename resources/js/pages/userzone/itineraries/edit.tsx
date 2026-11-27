import ItineraryForm, { type ItineraryFormData, type Place } from '@/components/itinerary-form';
import { type City } from '@/components/place-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Itinerary = {
    id: number;
    title: string;
    city_id: number;
    place_ids: number[];
};

type Props = {
    itinerary: Itinerary;
    cities: City[];
    places: Place[];
};

export default function Edit({ itinerary, cities, places }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Itineraries',
            href: '/user/itineraries',
        },
        {
            title: 'Edit Itinerary',
            href: `/user/itineraries/${itinerary.id}/edit`,
        },
    ];

    const { data, setData, put, processing, errors } = useForm<ItineraryFormData>({
        title: itinerary.title,
        city_id: String(itinerary.city_id),
        place_ids: itinerary.place_ids,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('userzone.itineraries.update', itinerary.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Itinerary" />
            <div className="p-4">
                <h1 className="text-xl font-semibold">Edit Itinerary</h1>

                <ItineraryForm data={data} setData={setData} errors={errors} cities={cities} places={places} onSubmit={submit}>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving…' : 'Save Itinerary'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('userzone.itineraries.index')}>Cancel</Link>
                    </Button>
                </ItineraryForm>
            </div>
        </AppLayout>
    );
}
