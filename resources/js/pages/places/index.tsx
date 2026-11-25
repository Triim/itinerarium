import SiteLayout from '@/layouts/site-layout';
import { Link } from '@inertiajs/react';

type Place = {
    id: number;
    name: string;
    itineraries_count: number;
};

type City = {
    id: number;
    name: string;
    places: Place[];
};

type Props = {
    cities: City[];
};

export default function Index({ cities }: Props) {
    return (
        <SiteLayout title="Places">
            <h1 className="text-3xl font-semibold">Places</h1>

            {cities.length > 0 ? (
                cities.map((city) => (
                    <section key={city.id} className="mt-8">
                        <h2 className="text-xl font-medium">{city.name}</h2>
                        <ul className="mt-4 divide-y divide-stone-200 border-y border-stone-200">
                            {city.places.map((place) => (
                                <li key={place.id} className="py-6">
                                    <h3 className="text-lg font-medium">
                                        <Link href={route('places.show', place.id)} className="underline">
                                            {place.name}
                                        </Link>
                                    </h3>
                                    <p className="mt-3 text-sm text-stone-500">
                                        {place.itineraries_count} {place.itineraries_count === 1 ? 'itinerary' : 'itineraries'}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))
            ) : (
                <p className="mt-8 text-stone-600">No places yet.</p>
            )}
        </SiteLayout>
    );
}
