import { Link } from '@inertiajs/react';

type Props = {
    itinerary: {
        title: string;
        slug: string;
        places_count: number;
        user: { name: string };
        city?: { name: string };
    };
    headingLevel?: 'h2' | 'h3';
};

// Shared by Welcome, the Itinerary index and City pages so every Itinerary looks the same.
export default function ItineraryCard({ itinerary, headingLevel: Heading = 'h2' }: Props) {
    const stops = itinerary.places_count;

    return (
        <Link
            href={route('itineraries.show', itinerary.slug)}
            className="group flex h-full flex-col rounded-xl border border-stone-200 bg-white p-5 transition-colors hover:border-stone-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900 sm:p-6"
        >
            {itinerary.city && <p className="text-xs font-medium tracking-widest break-words text-stone-500 uppercase">{itinerary.city.name}</p>}
            <Heading className={`text-lg font-semibold text-balance break-words sm:text-xl ${itinerary.city ? 'mt-2' : ''}`}>
                {itinerary.title}
            </Heading>
            <p className="mt-2 text-sm break-words text-stone-500">By {itinerary.user.name}</p>
            <div className="mt-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-1 pt-5 text-sm">
                <span className="text-stone-600">
                    {stops} {stops === 1 ? 'stop' : 'stops'}
                </span>
                <span className="font-medium text-stone-900 underline-offset-4 group-hover:underline">
                    View itinerary <span aria-hidden="true">&rarr;</span>
                </span>
            </div>
        </Link>
    );
}
