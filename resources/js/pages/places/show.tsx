import SiteLayout from '@/layouts/site-layout';
import { Link } from '@inertiajs/react';
import { Clapperboard } from 'lucide-react';

type Place = {
    id: number;
    name: string;
    description: string | null;
    image_url: string | null;
    themes: { id: string; label: string }[];
    films: string[];
};

type City = {
    id: number;
    name: string;
};

type Itinerary = {
    id: number;
    title: string;
    slug: string;
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
            <Link
                href={route('places.index', { city: city.id })}
                className="text-sm text-stone-600 underline underline-offset-4 hover:text-stone-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
            >
                <span aria-hidden="true">&larr;</span> Back to places in {city.name}
            </Link>

            <article className="mt-8">
                <p className="text-sm font-medium tracking-widest break-words text-stone-500 uppercase">
                    <Link
                        href={route('cities.show', city.id)}
                        className="hover:text-stone-900 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
                    >
                        {city.name}
                    </Link>
                </p>
                <h1 className="mt-2 text-3xl font-semibold text-balance break-words sm:text-4xl">{place.name}</h1>
                {place.image_url && <img src={place.image_url} alt={`Photo of ${place.name}`} className="mt-6 max-h-96 max-w-full rounded-lg" />}
                {place.description && <p className="mt-4 leading-relaxed break-words text-stone-600">{place.description}</p>}
                {place.themes.length > 0 && (
                    <ul aria-label="Themes" className="mt-4 flex flex-wrap gap-2">
                        {place.themes.map((theme) => (
                            <li key={theme.id} className="rounded-full bg-stone-100 px-3 py-1 text-sm text-stone-600">
                                {theme.label}
                            </li>
                        ))}
                    </ul>
                )}
            </article>

            {place.films.length > 0 && (
                <>
                    <h2 className="mt-8 flex items-center gap-2 text-xl font-semibold">
                        <Clapperboard aria-hidden="true" className="size-5 text-amber-700" />
                        On screen
                    </h2>
                    <ul className="mt-4 list-disc space-y-1 pl-5 break-words text-stone-700">
                        {place.films.map((film) => (
                            <li key={film}>
                                <cite>{film}</cite>
                            </li>
                        ))}
                    </ul>
                </>
            )}

            <h2 className="mt-8 text-xl font-semibold">Itineraries</h2>

            {itineraries.length > 0 ? (
                <ul className="mt-4 divide-y divide-stone-200 border-y border-stone-200">
                    {itineraries.map((itinerary) => (
                        <li key={itinerary.id} className="py-6">
                            <h3 className="text-lg font-semibold break-words">
                                <Link
                                    href={route('itineraries.show', itinerary.slug)}
                                    className="underline decoration-stone-300 underline-offset-4 hover:decoration-stone-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
                                >
                                    {itinerary.title}
                                </Link>
                            </h3>
                            <p className="mt-2 text-sm break-words text-stone-500">By {itinerary.user.name}</p>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="mt-4 text-stone-600">This place is not in an itinerary yet.</p>
            )}
        </SiteLayout>
    );
}
