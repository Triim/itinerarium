import SiteLayout from '@/layouts/site-layout';
import { Link } from '@inertiajs/react';

type Itinerary = {
    id: number;
    title: string;
    slug: string;
    places_count: number;
    city: { id: number; name: string };
    user: { id: number; name: string };
};

type Props = {
    itineraries: Itinerary[];
};

export default function Index({ itineraries }: Props) {
    return (
        <SiteLayout title="Itineraries">
            <header className="border-b border-stone-200 pb-6">
                <p className="text-sm font-medium tracking-widest text-stone-500 uppercase">City itineraries</p>
                <h1 className="mt-2 text-3xl font-semibold sm:text-4xl">Itineraries</h1>
                <p className="mt-3 max-w-2xl leading-relaxed text-stone-600">
                    Each itinerary is a set of places in one city, put together by a member of the community.
                </p>
            </header>

            {itineraries.length > 0 ? (
                <>
                    <p className="mt-6 text-sm text-stone-500">
                        {itineraries.length} {itineraries.length === 1 ? 'itinerary' : 'itineraries'}
                    </p>
                    <ul className="mt-4 grid gap-4 sm:grid-cols-2">
                        {itineraries.map((itinerary) => (
                            <li key={itinerary.id}>
                                <Link
                                    href={route('itineraries.show', itinerary.slug)}
                                    className="group flex h-full flex-col rounded-xl border border-stone-200 bg-white p-5 transition-colors hover:border-stone-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900 sm:p-6"
                                >
                                    <p className="text-xs font-medium tracking-widest break-words text-stone-500 uppercase">{itinerary.city.name}</p>
                                    <h2 className="mt-2 text-xl font-semibold text-balance break-words">{itinerary.title}</h2>
                                    <p className="mt-3 text-sm break-words text-stone-500">By {itinerary.user.name}</p>
                                    <div className="mt-auto flex items-center justify-between gap-4 pt-5 text-sm">
                                        <span className="text-stone-600">
                                            {itinerary.places_count} {itinerary.places_count === 1 ? 'place' : 'places'}
                                        </span>
                                        <span className="font-medium text-stone-900 underline-offset-4 group-hover:underline">
                                            View itinerary <span aria-hidden="true">&rarr;</span>
                                        </span>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </>
            ) : (
                <div className="mt-8 rounded-xl border border-dashed border-stone-300 p-6 text-stone-600">
                    <p>No itineraries have been shared yet.</p>
                </div>
            )}
        </SiteLayout>
    );
}
