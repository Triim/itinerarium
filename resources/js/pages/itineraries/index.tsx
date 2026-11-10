import { ItineraryCard } from '@/components/itinerary-card';
import { LayerToggles } from '@/components/map/layer-toggles';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type City, type Itinerary, type Layer, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface Props {
    itineraries: Paginated<Itinerary>;
    cities: Pick<City, 'id' | 'name' | 'slug'>[];
    layers: Layer[];
    filters: { city: string | null; layers: string[] };
}

const ALL = 'all';

export default function ItinerariesIndex({ itineraries, cities, layers, filters }: Props) {
    const apply = (next: Partial<Props['filters']>) => {
        const merged = { ...filters, ...next };
        router.get(
            route('itineraries.index'),
            { city: merged.city ?? undefined, layers: merged.layers },
            { preserveState: true, preserveScroll: true },
        );
    };

    const toggleLayer = (layer: Layer) =>
        apply({ layers: filters.layers.includes(layer.slug) ? filters.layers.filter((s) => s !== layer.slug) : [...filters.layers, layer.slug] });

    return (
        <AppLayout breadcrumbs={[{ title: 'Routes', href: route('itineraries.index') }]}>
            <Head title="Routes" />
            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Author routes</h1>
                    <p className="text-muted-foreground text-sm">
                        Filter by city and layers: a route matches if it has stops on any of the selected layers.
                    </p>
                </div>

                <div className="flex flex-col gap-3">
                    <Select value={filters.city ?? ALL} onValueChange={(v) => apply({ city: v === ALL ? null : v })}>
                        <SelectTrigger className="w-64">
                            <SelectValue placeholder="City" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All cities</SelectItem>
                            {cities.map((c) => (
                                <SelectItem key={c.id} value={c.slug}>
                                    {c.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <LayerToggles layers={layers} active={filters.layers} onToggle={toggleLayer} by="slug" />
                </div>

                {itineraries.data.length === 0 ? (
                    <p className="text-muted-foreground text-sm">Nothing found. Try changing the filters.</p>
                ) : (
                    <div className="grid gap-4 md:grid-cols-3">
                        {itineraries.data.map((it) => (
                            <ItineraryCard key={it.id} itinerary={it} />
                        ))}
                    </div>
                )}

                {itineraries.last_page > 1 && (
                    <nav className="flex flex-wrap gap-1">
                        {itineraries.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    preserveScroll
                                    className={cn('rounded-md border px-3 py-1 text-sm', link.active && 'bg-accent')}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span key={i} className="text-muted-foreground px-3 py-1 text-sm" dangerouslySetInnerHTML={{ __html: link.label }} />
                            ),
                        )}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
