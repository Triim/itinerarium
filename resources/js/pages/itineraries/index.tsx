import { Link } from '@inertiajs/react';
import SiteLayout from '@/layouts/site-layout';

type Itinerary = {
    id: number;
    title: string;
    city: { name: string };
    user: { name: string };
};

type Props = {
    itineraries: Itinerary[];
};

export default function Index({ itineraries }: Props) {
    return (
        <SiteLayout title="Itineraries">
            <h1 className="text-3xl font-semibold">Itineraries</h1>

            {itineraries.length > 0 ? (
                <ul className="mt-8 divide-y divide-stone-200 border-y border-stone-200">
                    {itineraries.map((itinerary) => (
                        <li key={itinerary.id} className="py-6">
                            <p className="text-sm text-stone-500">{itinerary.city.name}</p>
                            <h2 className="mt-1 text-xl font-medium">
                                <Link href={route('itineraries.show', itinerary.id)} className="underline">
                                    {itinerary.title}
                                </Link>
                            </h2>
                            <p className="mt-3 text-sm text-stone-500">By {itinerary.user.name}</p>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="mt-8 text-stone-600">No itineraries yet.</p>
            )}
        </SiteLayout>
    );
}
