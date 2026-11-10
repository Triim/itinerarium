import { ItineraryCard } from '@/components/itinerary-card';
import AppLayout from '@/layouts/app-layout';
import { type Selection } from '@/types';
import { Head } from '@inertiajs/react';

export default function SelectionShow({ selection }: { selection: Selection }) {
    const itineraries = selection.itineraries ?? [];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Collections', href: route('selections.index') },
                { title: selection.title, href: route('selections.show', selection.slug) },
            ]}
        >
            <Head title={selection.title} />
            <div className="flex flex-col gap-6 p-4">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold">{selection.title}</h1>
                    <p className="text-muted-foreground text-sm">
                        {selection.city ? selection.city.name : 'Various cities'} · curated by {selection.author?.name}
                    </p>
                    {selection.description && <p className="max-w-2xl text-sm">{selection.description}</p>}
                </div>
                <div className="grid gap-4 md:grid-cols-3">
                    {itineraries.map((it) => (
                        <ItineraryCard key={it.id} itinerary={it} />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
