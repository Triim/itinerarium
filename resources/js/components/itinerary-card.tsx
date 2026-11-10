import { LayerDot } from '@/components/map/layer-toggles';
import { Badge } from '@/components/ui/badge';
import { type Itinerary, type Layer } from '@/types';
import { Link } from '@inertiajs/react';
import { MapPin } from 'lucide-react';

export function ItineraryCard({ itinerary, showStatus = false }: { itinerary: Itinerary; showStatus?: boolean }) {
    const layers = Object.values(
        (itinerary.places ?? []).reduce<Record<number, Layer>>((acc, p) => (p.layer ? { ...acc, [p.layer.id]: p.layer } : acc), {}),
    );
    const count = itinerary.places_count ?? itinerary.places?.length ?? 0;

    return (
        <Link
            href={route('itineraries.show', itinerary.id)}
            className="border-sidebar-border/70 dark:border-sidebar-border hover:bg-accent/50 flex flex-col gap-2 rounded-xl border p-4 transition-colors"
        >
            <div className="flex items-start justify-between gap-2">
                <h3 className="font-semibold">{itinerary.title}</h3>
                {showStatus && (
                    <Badge variant={itinerary.is_published ? 'secondary' : 'outline'}>{itinerary.is_published ? 'Опубликован' : 'Черновик'}</Badge>
                )}
            </div>
            {itinerary.description && <p className="text-muted-foreground line-clamp-2 text-sm">{itinerary.description}</p>}
            <div className="text-muted-foreground mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                {itinerary.city && (
                    <span className="inline-flex items-center gap-1">
                        <MapPin className="size-3" />
                        {itinerary.city.name}
                    </span>
                )}
                <span>{count} точ.</span>
                {itinerary.author && <span>автор: {itinerary.author.name}</span>}
                {layers.length > 0 && (
                    <span className="inline-flex gap-1">
                        {layers.map((l) => (
                            <LayerDot key={l.id} layer={l} />
                        ))}
                    </span>
                )}
            </div>
        </Link>
    );
}
