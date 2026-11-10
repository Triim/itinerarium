import { cn } from '@/lib/utils';
import { type Layer } from '@/types';

interface LayerTogglesProps {
    layers: Layer[];
    active: (string | number)[];
    onToggle: (layer: Layer) => void;
    by?: 'id' | 'slug';
    counts?: Record<number, number>;
}

/** Chips to switch map layers on and off; also used as the route filter. */
export function LayerToggles({ layers, active, onToggle, by = 'id', counts }: LayerTogglesProps) {
    return (
        <div className="flex flex-wrap gap-2">
            {layers.map((layer) => {
                const on = active.includes(layer[by]);
                return (
                    <button
                        key={layer.id}
                        type="button"
                        title={layer.description ?? undefined}
                        onClick={() => onToggle(layer)}
                        aria-pressed={on}
                        className={cn(
                            'inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm transition-colors',
                            on ? 'bg-accent text-accent-foreground border-transparent' : 'text-muted-foreground opacity-70 hover:opacity-100',
                        )}
                    >
                        <span className="size-2.5 rounded-full" style={{ backgroundColor: layer.color, opacity: on ? 1 : 0.4 }} />
                        {layer.name}
                        {counts && <span className="text-muted-foreground text-xs">{counts[layer.id] ?? 0}</span>}
                    </button>
                );
            })}
        </div>
    );
}

export function LayerDot({ layer }: { layer?: Layer }) {
    if (!layer) return null;
    return <span className="inline-block size-2.5 shrink-0 rounded-full" style={{ backgroundColor: layer.color }} title={layer.name} />;
}
