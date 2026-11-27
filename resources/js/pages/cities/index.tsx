import CoverImage, { type Cover } from '@/components/cover-image';
import SiteLayout from '@/layouts/site-layout';
import { Link } from '@inertiajs/react';

type City = {
    id: number;
    name: string;
    itineraries_count: number;
    places_count: number;
    cover: Cover;
};

type Props = {
    cities: City[];
};

export default function Index({ cities }: Props) {
    return (
        <SiteLayout title="Cities">
            <header className="border-b border-stone-200 pb-6">
                <p className="text-sm font-medium tracking-widest text-stone-500 uppercase">Where to go</p>
                <h1 className="mt-2 text-3xl font-semibold sm:text-4xl">Cities</h1>
                <p className="mt-3 max-w-2xl leading-relaxed text-stone-600">Pick a city to see its itineraries and explore its places by theme.</p>
            </header>

            {cities.length > 0 ? (
                <>
                    <p className="mt-6 text-sm text-stone-500">
                        {cities.length} {cities.length === 1 ? 'city' : 'cities'}
                    </p>
                    <ul className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {cities.map((city) => (
                            <li key={city.id}>
                                <Link
                                    href={route('cities.show', city.id)}
                                    className="group flex h-full flex-col overflow-hidden rounded-xl border border-stone-200 bg-white transition-colors hover:border-stone-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
                                >
                                    <CoverImage cover={city.cover} className="aspect-[3/2]" lazy shortCredit />
                                    <div className="flex flex-1 flex-col p-5">
                                        <h2 className="text-xl font-semibold text-balance break-words">{city.name}</h2>
                                        <p className="mt-1.5 text-sm text-stone-600">
                                            {city.itineraries_count} {city.itineraries_count === 1 ? 'itinerary' : 'itineraries'} ·{' '}
                                            {city.places_count} {city.places_count === 1 ? 'place' : 'places'}
                                        </p>
                                        <span className="mt-auto pt-4 text-sm font-medium text-stone-900 underline-offset-4 group-hover:underline">
                                            View city <span aria-hidden="true">&rarr;</span>
                                        </span>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </>
            ) : (
                <div className="mt-8 rounded-xl border border-dashed border-stone-300 p-6 text-stone-600">
                    <p>No cities yet.</p>
                </div>
            )}
        </SiteLayout>
    );
}
