import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Places',
        href: '/user/places',
    },
];

type Place = {
    id: number;
    name: string;
    city: { name: string };
};

type Props = {
    places: Place[];
};

export default function Index({ places }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manage places" />
            <div className="p-4">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">Manage places</h1>
                    <Button asChild>
                        <Link href={route('userzone.places.create')}>Create Place</Link>
                    </Button>
                </div>

                {places.length > 0 ? (
                    <ul className="mt-4 divide-y border-y">
                        {places.map((place) => (
                            <li key={place.id} className="flex justify-between gap-4 py-3">
                                <span>{place.name}</span>
                                <span className="flex items-baseline gap-4">
                                    <span className="text-muted-foreground">{place.city.name}</span>
                                    <Link
                                        href={route('userzone.places.edit', place.id)}
                                        className="underline underline-offset-4"
                                        aria-label={`Edit ${place.name}`}
                                    >
                                        Edit
                                    </Link>
                                </span>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-muted-foreground mt-4">No places yet.</p>
                )}
            </div>
        </AppLayout>
    );
}
