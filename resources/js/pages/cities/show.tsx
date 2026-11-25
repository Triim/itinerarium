import SiteLayout from '@/layouts/site-layout';
import { Link } from '@inertiajs/react';

type City = {
    id: number;
    name: string;
};

type Itinerary = {
    id: number;
    title: string;
    user: { name: string };
};

type Props = {
    city: City;
    itineraries: Itinerary[];
};

export default function Show({ city, itineraries }: Props) {
    return (
        <SiteLayout title={city.name}>
            <Link href={route('cities.index')} className="text-sm underline">
                Back to cities
            </Link>

            <h1 className="mt-8 text-3xl font-semibold">{city.name}</h1>

            <h2 className="mt-8 text-xl font-medium">Itineraries</h2>

            {itineraries.length > 0 ? (
                <ul className="mt-4 divide-y divide-stone-200 border-y border-stone-200">
                    {itineraries.map((itinerary) => (
                        <li key={itinerary.id} className="py-6">
                            <h3 className="text-lg font-medium">
                                <Link href={route('itineraries.show', itinerary.id)} className="underline">
                                    {itinerary.title}
                                </Link>
                            </h3>
                            <p className="mt-3 text-sm text-stone-500">By {itinerary.user.name}</p>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="mt-4 text-stone-600">No itineraries yet.</p>
            )}
        </SiteLayout>
    );
}
