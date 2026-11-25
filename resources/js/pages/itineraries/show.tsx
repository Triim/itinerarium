import { Link } from '@inertiajs/react';
import SiteLayout from '@/layouts/site-layout';

type Place = {
    id: number;
    name: string;
};

type Itinerary = {
    title: string;
    city: { name: string };
    user: { name: string };
    places: Place[];
};

type Props = {
    itinerary: Itinerary;
};

export default function Show({ itinerary }: Props) {
    return (
        <SiteLayout title={itinerary.title}>
            <Link href={route('itineraries.index')} className="text-sm underline">
                Back to itineraries
            </Link>

            <article className="mt-8">
                <p className="text-sm text-stone-500">{itinerary.city.name}</p>
                <h1 className="mt-1 text-3xl font-semibold">{itinerary.title}</h1>
                <p className="mt-4 text-sm text-stone-500">By {itinerary.user.name}</p>
            </article>

            <h2 className="mt-8 text-xl font-medium">Places</h2>

            {itinerary.places.length > 0 ? (
                <ul className="mt-4 divide-y divide-stone-200 border-y border-stone-200">
                    {itinerary.places.map((place) => (
                        <li key={place.id} className="py-6">
                            <h3 className="text-lg font-medium">
                                <Link href={route('places.show', place.id)} className="underline">
                                    {place.name}
                                </Link>
                            </h3>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="mt-4 text-stone-600">No places yet.</p>
            )}
        </SiteLayout>
    );
}
