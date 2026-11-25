import PlaceForm, { type City } from '@/components/place-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Places',
        href: '/user/places',
    },
    {
        title: 'Create Place',
        href: '/user/places/create',
    },
];

type Props = {
    cities: City[];
};

export default function Create({ cities }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        city_id: '',
        latitude: '',
        longitude: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('userzone.places.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Place" />
            <div className="p-4">
                <h1 className="text-xl font-semibold">Create Place</h1>

                <PlaceForm data={data} setData={setData} errors={errors} cities={cities} onSubmit={submit}>
                    <Button type="submit" disabled={processing}>
                        Create Place
                    </Button>
                </PlaceForm>
            </div>
        </AppLayout>
    );
}
