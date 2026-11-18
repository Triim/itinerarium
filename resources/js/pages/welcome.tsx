import { Link, usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';
import SiteLayout from '@/layouts/site-layout';

type FeaturedItinerary = {
    title: string;
    city: { name: string };
    user: { name: string };
};

type WelcomeProps = SharedData & {
    itinerary: FeaturedItinerary | null;
};

export default function Welcome() {
    const { auth, itinerary } = usePage<WelcomeProps>().props;

    return (
        <SiteLayout
            title="Itinerarium"
            actions={auth.user ? (
                <Link href={route('dashboard')}>Dashboard</Link>
            ) : (
                <>
                    <Link href={route('login')}>Log in</Link>
                    <Link href={route('register')}>Register</Link>
                </>
            )}
        >
            <section className="mt-12">
                <p className="text-sm font-medium uppercase tracking-widest text-stone-500">
                    City itineraries
                </p>
                <h1 className="mt-3 text-4xl font-semibold">Find a route for your next trip</h1>

                {itinerary ? (
                    <article className="mt-10 rounded-xl border border-stone-200 bg-white p-6">
                        <p className="text-sm text-stone-500">{itinerary.city.name}</p>
                        <h2 className="mt-2 text-2xl font-semibold">{itinerary.title}</h2>
                        <p className="mt-4 text-sm text-stone-500">By {itinerary.user.name}</p>
                    </article>
                ) : (
                    <p className="mt-10 text-stone-600">No itineraries yet.</p>
                )}
            </section>
        </SiteLayout>
    );
}
