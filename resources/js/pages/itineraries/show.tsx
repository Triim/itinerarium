import { Head, Link } from '@inertiajs/react';

type Itinerary = {
    title: string;
    city: { name: string };
    user: { name: string };
};

type Props = {
    itinerary: Itinerary;
};

export default function Show({ itinerary }: Props) {
    return (
        <>
            <Head title={itinerary.title} />

            <main className="min-h-screen bg-stone-50 px-6 py-8 text-stone-900">
                <div className="mx-auto max-w-4xl">
                    <Link href={route('itineraries.index')} className="text-sm underline">
                        Back to itineraries
                    </Link>

                    <article className="mt-8">
                        <p className="text-sm text-stone-500">{itinerary.city.name}</p>
                        <h1 className="mt-1 text-3xl font-semibold">{itinerary.title}</h1>
                        <p className="mt-4 text-sm text-stone-500">By {itinerary.user.name}</p>
                    </article>
                </div>
            </main>
        </>
    );
}
