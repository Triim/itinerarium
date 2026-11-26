import SiteLayout from '@/layouts/site-layout';
import { Link } from '@inertiajs/react';

type City = {
    id: number;
    name: string;
    itineraries_count: number;
    places_count: number;
};

type Itinerary = {
    id: number;
    title: string;
    places_count: number;
    user: { id: number; name: string };
};

type Props = {
    city: City;
    itineraries: Itinerary[];
};

export default function Show({ city, itineraries }: Props) {
    return (
        <SiteLayout title={city.name}>
            <Link
                href={route('cities.index')}
                className="text-sm text-stone-600 underline underline-offset-4 hover:text-stone-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
            >
                <span aria-hidden="true">&larr;</span> Back to cities
            </Link>

            <header className="mt-8 border-b border-stone-200 pb-8">
                <p className="text-sm font-medium tracking-widest text-stone-500 uppercase">City</p>
                <h1 className="mt-2 text-3xl font-semibold text-balance break-words sm:text-4xl">{city.name}</h1>
                <p className="mt-4 text-sm text-stone-600">
                    {city.itineraries_count} {city.itineraries_count === 1 ? 'itinerary' : 'itineraries'} · {city.places_count}{' '}
                    {city.places_count === 1 ? 'place' : 'places'}
                </p>

                {city.places_count > 0 ? (
                    <Link
                        href={route('places.index', { city: city.id })}
                        className="mt-6 inline-flex max-w-full items-center gap-2 rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-medium break-words text-white transition-colors hover:bg-stone-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
                    >
                        <span>
                            Explore places in {city.name} by theme <span aria-hidden="true">&rarr;</span>
                        </span>
                    </Link>
                ) : (
                    <p className="mt-6 text-sm text-stone-500">No places have been added to this city yet.</p>
                )}
            </header>

            <section aria-labelledby="city-itineraries" className="mt-8">
                <h2 id="city-itineraries" className="text-xl font-semibold">
                    Itineraries
                </h2>

                {itineraries.length > 0 ? (
                    <ul className="mt-4 grid gap-4 sm:grid-cols-2">
                        {itineraries.map((itinerary) => (
                            <li key={itinerary.id}>
                                <Link
                                    href={route('itineraries.show', itinerary.id)}
                                    className="group flex h-full flex-col rounded-xl border border-stone-200 bg-white p-5 transition-colors hover:border-stone-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900 sm:p-6"
                                >
                                    <h3 className="text-lg font-semibold text-balance break-words">{itinerary.title}</h3>
                                    <p className="mt-3 text-sm break-words text-stone-500">By {itinerary.user.name}</p>
                                    <div className="mt-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-1 pt-5 text-sm">
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
                ) : (
                    <div className="mt-4 rounded-xl border border-dashed border-stone-300 p-6 text-stone-600">
                        <p>No itineraries in {city.name} yet.</p>
                    </div>
                )}
            </section>
        </SiteLayout>
    );
}
