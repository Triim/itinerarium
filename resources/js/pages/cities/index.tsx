import SiteLayout from '@/layouts/site-layout';
import { Link } from '@inertiajs/react';

type City = {
    id: number;
    name: string;
    itineraries_count: number;
};

type Props = {
    cities: City[];
};

export default function Index({ cities }: Props) {
    return (
        <SiteLayout title="Cities">
            <h1 className="text-3xl font-semibold">Cities</h1>

            {cities.length > 0 ? (
                <ul className="mt-8 divide-y divide-stone-200 border-y border-stone-200">
                    {cities.map((city) => (
                        <li key={city.id} className="py-6">
                            <h2 className="text-xl font-medium">
                                <Link href={route('cities.show', city.id)} className="underline">
                                    {city.name}
                                </Link>
                            </h2>
                            <p className="mt-3 text-sm text-stone-500">
                                {city.itineraries_count} {city.itineraries_count === 1 ? 'itinerary' : 'itineraries'}
                            </p>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="mt-8 text-stone-600">No cities yet.</p>
            )}
        </SiteLayout>
    );
}
