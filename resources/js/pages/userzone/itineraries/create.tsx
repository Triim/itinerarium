import ItineraryForm, { type ItineraryFormData, type Place } from '@/components/itinerary-form';
import { type City } from '@/components/place-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Itineraries',
        href: '/user/itineraries',
    },
    {
        title: 'Create Itinerary',
        href: '/user/itineraries/create',
    },
];

type Props = {
    cities: City[];
    places: Place[];
};

export default function Create({ cities, places }: Props) {
    const { data, setData, post, processing, errors } = useForm<ItineraryFormData>({
        title: '',
        city_id: '',
        place_ids: [],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('userzone.itineraries.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Itinerary" />
            <div className="p-4">
                <h1 className="text-xl font-semibold">Create Itinerary</h1>

                <ItineraryForm data={data} setData={setData} errors={errors} cities={cities} places={places} onSubmit={submit}>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Creating…' : 'Create Itinerary'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('userzone.itineraries.index')}>Cancel</Link>
                    </Button>
                </ItineraryForm>
            </div>
        </AppLayout>
    );
}
