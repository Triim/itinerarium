import SiteLayout from '@/layouts/site-layout';
import { Link } from '@inertiajs/react';

type Place = {
    id: number;
    name: string;
    description: string | null;
    image_url: string | null;
};

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
    place: Place;
    city: City;
    itineraries: Itinerary[];
};

export default function Show({ place, city, itineraries }: Props) {
    return (
        <SiteLayout title={place.name}>
            <Link href={route('places.index')} className="text-sm underline">
                Back to places
            </Link>

            <article className="mt-8">
                <p className="text-sm text-stone-500">
                    <Link href={route('cities.show', city.id)} className="underline">
                        {city.name}
                    </Link>
                </p>
                <h1 className="mt-1 text-3xl font-semibold">{place.name}</h1>
                {place.image_url && <img src={place.image_url} alt={`Photo of ${place.name}`} className="mt-4 max-h-96 max-w-full rounded-md" />}
                {place.description && <p className="mt-4 text-stone-600">{place.description}</p>}
            </article>

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
