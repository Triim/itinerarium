import PlaceForm, { type City, type PlaceFormData, type ThemeOption } from '@/components/place-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
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
    theme_options: ThemeOption[];
};

export default function Create({ cities, theme_options }: Props) {
    const { data, setData, post, processing, errors } = useForm<PlaceFormData>({
        name: '',
        description: '',
        city_id: '',
        latitude: '',
        longitude: '',
        themes: [],
        films: [],
        image: null,
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

                <PlaceForm data={data} setData={setData} errors={errors} cities={cities} themeOptions={theme_options} onSubmit={submit}>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Creating…' : 'Create Place'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('userzone.places.index')}>Cancel</Link>
                    </Button>
                </PlaceForm>
            </div>
        </AppLayout>
    );
}
