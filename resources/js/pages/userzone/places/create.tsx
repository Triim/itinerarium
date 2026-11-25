import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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

type City = {
    id: number;
    name: string;
};

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

                <form onSubmit={submit} className="mt-4 max-w-xl space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            className="border-input focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                            rows={5}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            required
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="city_id">City</Label>
                        <Select value={data.city_id} onValueChange={(value) => setData('city_id', value)}>
                            <SelectTrigger id="city_id">
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
                        <InputError message={errors.city_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="latitude">Latitude</Label>
                        <Input
                            id="latitude"
                            type="number"
                            step="any"
                            value={data.latitude}
                            onChange={(e) => setData('latitude', e.target.value)}
                            required
                        />
                        <InputError message={errors.latitude} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="longitude">Longitude</Label>
                        <Input
                            id="longitude"
                            type="number"
                            step="any"
                            value={data.longitude}
                            onChange={(e) => setData('longitude', e.target.value)}
                            required
                        />
                        <InputError message={errors.longitude} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        Create Place
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
