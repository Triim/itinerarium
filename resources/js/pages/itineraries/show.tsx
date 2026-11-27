import SiteLayout from '@/layouts/site-layout';
import { Link } from '@inertiajs/react';
import { Clapperboard, ImageOff } from 'lucide-react';

type Place = {
    id: number;
    name: string;
    description: string | null;
    image_url: string | null;
    themes: { id: string; label: string }[];
    films: string[];
};

type Itinerary = {
    id: number;
    title: string;
    city: { id: number; name: string };
    user: { id: number; name: string };
    places: Place[];
};

type Props = {
    itinerary: Itinerary;
};

export default function Show({ itinerary }: Props) {
    const count = itinerary.places.length;

    return (
        <SiteLayout title={itinerary.title}>
            <Link
                href={route('itineraries.index')}
                className="text-sm text-stone-600 underline underline-offset-4 hover:text-stone-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
            >
                <span aria-hidden="true">&larr;</span> Back to itineraries
            </Link>

            <header className="mt-8 border-b border-stone-200 pb-8">
                <p className="text-sm font-medium tracking-widest break-words text-stone-500 uppercase">
                    <Link
                        href={route('cities.show', itinerary.city.id)}
                        className="hover:text-stone-900 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
                    >
                        {itinerary.city.name}
                    </Link>
                </p>
                <h1 className="mt-2 text-3xl font-semibold text-balance break-words sm:text-4xl">{itinerary.title}</h1>
                <p className="mt-4 text-sm break-words text-stone-600">
                    By <span className="font-medium text-stone-900">{itinerary.user.name}</span> · {count} {count === 1 ? 'stop' : 'stops'}
                </p>
            </header>

            <section aria-labelledby="itinerary-places" className="mt-8">
                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h2 id="itinerary-places" className="text-xl font-semibold">
                        Stops
                    </h2>
                    {count > 1 && <p className="text-sm text-stone-500">In the order chosen by the author</p>}
                </div>

                {count > 0 ? (
                    <ol className="mt-4 divide-y divide-stone-200 border-y border-stone-200">
                        {itinerary.places.map((place, position) => (
                            <li key={place.id} className="flex gap-3 py-6 sm:gap-4">
                                {/* The <ol> already announces the position; the badge is visual. */}
                                <span
                                    aria-hidden="true"
                                    className="flex size-7 shrink-0 items-center justify-center rounded-full bg-stone-900 text-sm font-semibold text-white tabular-nums"
                                >
                                    {position + 1}
                                </span>

                                <div className="flex min-w-0 flex-1 flex-col gap-4 sm:flex-row sm:gap-6">
                                    <div className="min-w-0 flex-1">
                                        <h3 className="text-lg font-semibold break-words">
                                            <Link
                                                href={route('places.show', place.id)}
                                                className="underline decoration-stone-300 underline-offset-4 hover:decoration-stone-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
                                            >
                                                {place.name}
                                            </Link>
                                        </h3>

                                        {place.description && <p className="mt-2 leading-relaxed break-words text-stone-600">{place.description}</p>}

                                        {place.films.length > 0 && (
                                            <p className="mt-3 flex gap-2 text-sm text-stone-800">
                                                <Clapperboard aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-amber-700" />
                                                <span className="min-w-0 break-words">
                                                    <span className="font-medium">On screen: </span>
                                                    {place.films.map((film, index) => (
                                                        <span key={film}>
                                                            {index > 0 && ', '}
                                                            <cite>{film}</cite>
                                                        </span>
                                                    ))}
                                                </span>
                                            </p>
                                        )}

                                        {place.themes.length > 0 && (
                                            <ul aria-label="Themes" className="mt-3 flex flex-wrap gap-1.5">
                                                {place.themes.map((theme) => (
                                                    <li key={theme.id} className="rounded-full bg-stone-100 px-2.5 py-0.5 text-xs text-stone-600">
                                                        {theme.label}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>

                                    {/* On phones the photo sits above the text; from sm it moves to the right. */}
                                    {place.image_url ? (
                                        <img
                                            src={place.image_url}
                                            alt={`Photo of ${place.name}`}
                                            loading="lazy"
                                            className="order-first aspect-[4/3] w-full max-w-72 shrink-0 self-start rounded-lg bg-stone-100 object-cover sm:order-none sm:w-40"
                                        />
                                    ) : (
                                        <div
                                            aria-hidden="true"
                                            className="hidden aspect-[4/3] w-40 shrink-0 items-center justify-center self-start rounded-lg bg-stone-100 text-stone-400 sm:flex"
                                        >
                                            <ImageOff className="size-6" />
                                        </div>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ol>
                ) : (
                    <p className="mt-4 rounded-xl border border-dashed border-stone-300 p-6 text-stone-600">No stops yet.</p>
                )}
            </section>
        </SiteLayout>
    );
}
