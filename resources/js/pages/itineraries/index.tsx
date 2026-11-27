import ItineraryCard from '@/components/itinerary-card';
import SiteLayout from '@/layouts/site-layout';

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
                    <ul className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {itineraries.map((itinerary) => (
                            <li key={itinerary.id}>
                                <ItineraryCard itinerary={itinerary} />
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
