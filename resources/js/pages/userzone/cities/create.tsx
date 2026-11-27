import CityForm from '@/components/city-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Cities',
        href: '/user/cities',
    },
    {
        title: 'Create City',
        href: '/user/cities/create',
    },
];

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('userzone.cities.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create City" />
            <div className="p-4">
                <h1 className="text-xl font-semibold">Create City</h1>

                <CityForm data={data} setData={setData} errors={errors} onSubmit={submit}>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Creating…' : 'Create City'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('userzone.cities.index')}>Cancel</Link>
                    </Button>
                </CityForm>
            </div>
        </AppLayout>
    );
}
