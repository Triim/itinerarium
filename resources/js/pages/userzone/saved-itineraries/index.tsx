import { type Cover } from '@/components/cover-image';
import ItineraryCard from '@/components/itinerary-card';
import SiteLayout from '@/layouts/site-layout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Itinerary = {
    id: number;
    title: string;
    slug: string;
    places_count: number;
    city: { id: number; name: string };
    user: { id: number; name: string };
    cover: Cover;
};

type Props = {
    itineraries: Itinerary[];
};

const focusRing = 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900';

// Itineraries by other authors that the user saved; their own are under My Itineraries.
export default function Index({ itineraries }: Props) {
    const [processing, setProcessing] = useState<number | null>(null);
    const [message, setMessage] = useState('');

    const remove = (itinerary: Itinerary) => {
        router.delete(route('userzone.saved-itineraries.destroy', itinerary.id), {
            preserveScroll: true,
            onStart: () => setProcessing(itinerary.id),
            onFinish: () => setProcessing(null),
            onSuccess: () => setMessage(`Removed "${itinerary.title}" from your saved itineraries.`),
        });
    };

    return (
        <SiteLayout title="Saved itineraries">
            <header className="border-b border-stone-200 pb-6">
                <p className="text-sm font-medium tracking-widest text-stone-500 uppercase">Your collection</p>
                <h1 className="mt-2 text-3xl font-semibold sm:text-4xl">Saved itineraries</h1>
                <p className="mt-3 max-w-2xl leading-relaxed text-stone-600">Itineraries by other travellers that you saved for later.</p>
            </header>

            {/* Always rendered, so screen readers announce the message when it appears. */}
            <p role="status" className="mt-6 text-sm break-words text-stone-600 empty:mt-0">
                {message}
            </p>

            {itineraries.length > 0 ? (
                <>
                    <p className="mt-6 text-sm text-stone-500">
                        {itineraries.length} saved {itineraries.length === 1 ? 'itinerary' : 'itineraries'}
                    </p>
                    <ul className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {itineraries.map((itinerary) => (
                            <li key={itinerary.id} className="flex flex-col gap-2">
                                <div className="flex-1">
                                    <ItineraryCard itinerary={itinerary} />
                                </div>
                                <button
                                    type="button"
                                    onClick={() => remove(itinerary)}
                                    disabled={processing === itinerary.id}
                                    aria-label={`Remove ${itinerary.title} from saved itineraries`}
                                    className={`cursor-pointer self-start text-sm text-stone-600 underline underline-offset-4 hover:text-stone-900 disabled:cursor-wait disabled:opacity-60 ${focusRing}`}
                                >
                                    Remove from saved
                                </button>
                            </li>
                        ))}
                    </ul>
                </>
            ) : (
                <div className="mt-8 rounded-xl border border-dashed border-stone-300 p-6 text-stone-600">
                    <p>You have not saved any itineraries yet.</p>
                    <p className="mt-2">
                        <Link href={route('itineraries.index')} className={`font-medium text-stone-900 underline underline-offset-4 ${focusRing}`}>
                            Browse itineraries
                        </Link>{' '}
                        and save the ones you would like to follow.
                    </p>
                </div>
            )}
        </SiteLayout>
    );
}
