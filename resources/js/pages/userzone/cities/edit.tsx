import CityForm from '@/components/city-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Props = {
    city: {
        id: number;
        name: string;
    };
};

export default function Edit({ city }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Cities',
            href: '/user/cities',
        },
        {
            title: 'Edit City',
            href: `/user/cities/${city.id}/edit`,
        },
    ];

    const { data, setData, put, processing, errors } = useForm({
        name: city.name,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('userzone.cities.update', city.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit City" />
            <div className="p-4">
                <h1 className="text-xl font-semibold">Edit City</h1>

                <CityForm data={data} setData={setData} errors={errors} onSubmit={submit}>
                    <div className="flex gap-4">
                        <Button type="submit" disabled={processing}>
                            Save City
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={route('userzone.cities.index')}>Cancel</Link>
                        </Button>
                    </div>
                </CityForm>
            </div>
        </AppLayout>
    );
}
