import SiteLayout from '@/layouts/site-layout';
import { Link, router, useRemember } from '@inertiajs/react';
import { ImageOff } from 'lucide-react';

type Theme = {
    id: string;
    label: string;
};

type Place = {
    id: number;
    name: string;
    itineraries_count: number;
    image_url: string | null;
    themes: string[];
};

type City = {
    id: number;
    name: string;
    places: Place[];
};

type Props = {
    cities: City[];
    themes: Theme[];
    selected_city: number | null;
};

const focusRing = 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900';

export default function Index({ cities, themes, selected_city }: Props) {
    // The theme is remembered in history, so returning from a place keeps it.
    const [theme, setTheme] = useRemember<string | null>(null, 'places.theme');

    const labels = Object.fromEntries(themes.map((option) => [option.id, option.label]));
    const selectedCity = cities.find((city) => city.id === selected_city) ?? null;
    const citiesInScope = selectedCity ? [selectedCity] : cities;
    const placesInScope = citiesInScope.flatMap((city) => city.places);
    const matches = (place: Place) => theme === null || place.themes.includes(theme);
    const visibleCities = citiesInScope.map((city) => ({ ...city, places: city.places.filter(matches) })).filter((city) => city.places.length > 0);
    const count = visibleCities.reduce((total, city) => total + city.places.length, 0);

    // Keep ?city= in the address without a server round trip, so the view can be shared and survives a reload.
    const selectCity = (cityId: number | null) =>
        router.replace({
            url: route('places.index', cityId === null ? {} : { city: cityId }),
            props: (props) => ({ ...props, selected_city: cityId }),
            preserveScroll: true,
            preserveState: true,
        });

    const reset = () => {
        setTheme(null);
        selectCity(null);
    };

    const scopeName = selectedCity ? selectedCity.name : 'all cities';

    return (
        <SiteLayout title="Places">
            <header className="border-b border-stone-200 pb-6">
                <p className="text-sm font-medium tracking-widest text-stone-500 uppercase">What to see</p>
                <h1 className="mt-2 text-3xl font-semibold sm:text-4xl">Places</h1>
                <p className="mt-3 max-w-2xl leading-relaxed text-stone-600">Browse the places behind our itineraries, by city and by theme.</p>
            </header>

            {cities.length > 0 ? (
                <>
                    <div className="mt-6 space-y-4">
                        <label htmlFor="city-filter" className="flex w-full flex-col gap-1.5 text-sm font-medium text-stone-700 sm:w-64">
                            City
                            <select
                                id="city-filter"
                                value={selected_city ?? ''}
                                onChange={(event) => selectCity(event.target.value === '' ? null : Number(event.target.value))}
                                className={`w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-base font-normal text-stone-900 sm:text-sm ${focusRing}`}
                            >
                                <option value="">All cities</option>
                                {cities.map((city) => (
                                    <option key={city.id} value={city.id}>
                                        {city.name}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <fieldset>
                            <legend className="text-sm font-medium text-stone-700">Theme</legend>
                            <div className="mt-1.5 flex flex-wrap gap-2">
                                {[{ id: null, label: 'All themes' }, ...themes].map((option) => {
                                    const active = theme === option.id;
                                    const total =
                                        option.id === null
                                            ? placesInScope.length
                                            : placesInScope.filter((place) => place.themes.includes(option.id)).length;

                                    return (
                                        <button
                                            key={option.id ?? 'all'}
                                            type="button"
                                            aria-pressed={active}
                                            onClick={() => setTheme(option.id)}
                                            className={`rounded-full border px-3 py-1.5 text-sm transition-colors ${focusRing} ${
                                                active
                                                    ? 'border-stone-900 bg-stone-900 text-white'
                                                    : 'border-stone-300 bg-white text-stone-700 hover:border-stone-500'
                                            }`}
                                        >
                                            {option.label} <span className={active ? 'text-stone-300' : 'text-stone-500'}>{total}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        </fieldset>
                    </div>

                    <div className="mt-6 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-stone-200 pb-3">
                        <p role="status" className="text-sm text-stone-600">
                            {count} {count === 1 ? 'place' : 'places'} in {scopeName}
                            {theme !== null && <> · {labels[theme]}</>}
                        </p>
                        {(theme !== null || selectedCity !== null) && (
                            <button
                                type="button"
                                onClick={reset}
                                className={`text-sm text-stone-600 underline underline-offset-4 hover:text-stone-900 ${focusRing}`}
                            >
                                Show all places
                            </button>
                        )}
                    </div>

                    {count > 0 ? (
                        visibleCities.map((city) => (
                            <section key={city.id} aria-labelledby={`city-${city.id}`} className="mt-8">
                                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                    <h2 id={`city-${city.id}`} className="text-xl font-semibold break-words">
                                        {city.name}
                                    </h2>
                                    <Link
                                        href={route('cities.show', city.id)}
                                        className={`text-sm text-stone-600 underline underline-offset-4 hover:text-stone-900 ${focusRing}`}
                                    >
                                        City itineraries <span aria-hidden="true">&rarr;</span>
                                    </Link>
                                </div>
                                <ul className="mt-3 divide-y divide-stone-200 border-y border-stone-200">
                                    {city.places.map((place) => (
                                        <li key={place.id} className="flex items-start gap-4 py-4">
                                            <div className="min-w-0 flex-1">
                                                <h3 className="font-semibold break-words">
                                                    <Link
                                                        href={route('places.show', place.id)}
                                                        className={`underline decoration-stone-300 underline-offset-4 hover:decoration-stone-900 ${focusRing}`}
                                                    >
                                                        {place.name}
                                                    </Link>
                                                </h3>
                                                {place.themes.length > 0 && (
                                                    <ul aria-label="Themes" className="mt-2 flex flex-wrap gap-1.5">
                                                        {place.themes.map((id) => (
                                                            <li
                                                                key={id}
                                                                className={`rounded-full px-2.5 py-0.5 text-xs ${
                                                                    id === theme ? 'bg-stone-900 text-white' : 'bg-stone-100 text-stone-600'
                                                                }`}
                                                            >
                                                                {labels[id]}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                )}
                                                <p className="mt-2 text-sm text-stone-500">
                                                    {place.itineraries_count === 0
                                                        ? 'Not in an itinerary yet'
                                                        : `In ${place.itineraries_count} ${place.itineraries_count === 1 ? 'itinerary' : 'itineraries'}`}
                                                </p>
                                            </div>
                                            {place.image_url ? (
                                                <img
                                                    src={place.image_url}
                                                    alt={`Photo of ${place.name}`}
                                                    loading="lazy"
                                                    className="aspect-[4/3] w-20 shrink-0 rounded-lg bg-stone-100 object-cover sm:w-28"
                                                />
                                            ) : (
                                                <div
                                                    aria-hidden="true"
                                                    className="flex aspect-[4/3] w-20 shrink-0 items-center justify-center rounded-lg bg-stone-100 text-stone-400 sm:w-28"
                                                >
                                                    <ImageOff className="size-5" />
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))
                    ) : (
                        <div className="mt-8 rounded-xl border border-dashed border-stone-300 p-6 text-stone-600">
                            <p>
                                No places in {scopeName} match {theme !== null ? labels[theme] : 'these filters'}.
                            </p>
                            <div className="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                                {theme !== null && (
                                    <button
                                        type="button"
                                        onClick={() => setTheme(null)}
                                        className={`underline underline-offset-4 hover:text-stone-900 ${focusRing}`}
                                    >
                                        Show all themes in {scopeName}
                                    </button>
                                )}
                                {selectedCity !== null && (
                                    <button
                                        type="button"
                                        onClick={() => selectCity(null)}
                                        className={`underline underline-offset-4 hover:text-stone-900 ${focusRing}`}
                                    >
                                        Search all cities
                                    </button>
                                )}
                            </div>
                        </div>
                    )}
                </>
            ) : (
                <div className="mt-8 rounded-xl border border-dashed border-stone-300 p-6 text-stone-600">
                    <p>No places yet.</p>
                </div>
            )}
        </SiteLayout>
    );
}
