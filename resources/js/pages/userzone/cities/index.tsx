import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Cities',
        href: '/user/cities',
    },
];

type City = {
    id: number;
    name: string;
    places_count: number;
    itineraries_count: number;
};

type Props = {
    cities: City[];
};

export default function Index({ cities }: Props) {
    const { errors } = usePage().props;

    const isOccupied = (city: City) => city.places_count > 0 || city.itineraries_count > 0;

    const destroy = (city: City) => {
        if (window.confirm(`Delete "${city.name}"? This cannot be undone.`)) {
            router.delete(route('userzone.cities.destroy', city.id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manage cities" />
            <div className="p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">Manage cities</h1>
                    <Button asChild>
                        <Link href={route('userzone.cities.create')}>Create City</Link>
                    </Button>
                </div>

                <InputError message={errors.city} className="mt-4 [overflow-wrap:anywhere]" role="alert" />

                {cities.some(isOccupied) && (
                    <p className="text-muted-foreground mt-4 text-sm">Cities with places or itineraries cannot be deleted.</p>
                )}

                {cities.length > 0 ? (
                    <ul className="mt-4 divide-y border-y">
                        {cities.map((city) => (
                            <li key={city.id} className="flex flex-wrap justify-between gap-x-4 gap-y-1 py-3">
                                <span className="min-w-0 [overflow-wrap:anywhere]">{city.name}</span>
                                <span className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                                    <span className="text-muted-foreground">
                                        {city.places_count} {city.places_count === 1 ? 'place' : 'places'}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {city.itineraries_count} {city.itineraries_count === 1 ? 'itinerary' : 'itineraries'}
                                    </span>
                                    <Link
                                        href={route('userzone.cities.edit', city.id)}
                                        className="underline underline-offset-4"
                                        aria-label={`Edit ${city.name}`}
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={() => destroy(city)}
                                        disabled={isOccupied(city)}
                                        className="text-destructive disabled:text-muted-foreground cursor-pointer underline underline-offset-4 disabled:cursor-not-allowed disabled:no-underline disabled:opacity-50"
                                        aria-label={`Delete ${city.name}`}
                                    >
                                        Delete
                                    </button>
                                </span>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-muted-foreground mt-4">No cities yet.</p>
                )}
            </div>
        </AppLayout>
    );
}
