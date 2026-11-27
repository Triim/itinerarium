import { type Cover } from '@/components/cover-image';
import ItineraryCard from '@/components/itinerary-card';
import SiteLayout from '@/layouts/site-layout';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

type WelcomeItinerary = {
    id: number;
    title: string;
    slug: string;
    places_count: number;
    city: { name: string };
    user: { name: string };
    cover: Cover;
};

type WelcomeProps = SharedData & {
    // True when the list is ranked by saves; with no saves yet it is the newest itineraries.
    popular: boolean;
    itineraries: WelcomeItinerary[];
};

export default function Welcome() {
    const { auth, popular, itineraries } = usePage<WelcomeProps>().props;

    return (
        <SiteLayout title="Itinerarium">
            <section className="mt-8 max-w-2xl sm:mt-12">
                <p className="text-sm font-medium tracking-widest text-stone-500 uppercase">City itineraries</p>
                <h1 className="mt-3 text-3xl font-semibold sm:text-4xl">Find a route for your next trip</h1>
                <p className="mt-4 leading-relaxed text-stone-600">
                    Discover itineraries for different cities. Each itinerary brings together a set of places to visit in one city, shared by a member
                    of the community.
                </p>
                <Link
                    href={route('itineraries.index')}
                    className="mt-6 inline-block rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
                >
                    Browse all itineraries
                </Link>
            </section>

            <section className="mt-14" aria-labelledby="welcome-itineraries">
                <h2 id="welcome-itineraries" className="text-sm font-medium tracking-widest text-stone-500 uppercase">
                    {popular ? 'Popular itineraries' : 'Recently added'}
                </h2>

                {itineraries.length > 0 ? (
                    <ul className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {itineraries.map((itinerary) => (
                            <li key={itinerary.id}>
                                <ItineraryCard itinerary={itinerary} headingLevel="h3" />
                            </li>
                        ))}
                    </ul>
                ) : (
                    <div className="mt-4 rounded-xl border border-dashed border-stone-300 p-6 text-stone-600">
                        <p>No itineraries have been shared yet.</p>
                        <p className="mt-2 text-sm">
                            {auth.user ? (
                                <Link href={route('userzone.itineraries.create')} className="underline">
                                    Create the first itinerary
                                </Link>
                            ) : (
                                <>
                                    <Link href={route('login')} className="underline">
                                        Log in
                                    </Link>{' '}
                                    to create the first itinerary.
                                </>
                            )}
                        </p>
                    </div>
                )}
            </section>
        </SiteLayout>
    );
}
