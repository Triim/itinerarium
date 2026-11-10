import { type Layer, type Place } from '@/types';
import 'leaflet/dist/leaflet.css';
import { type ReactNode, useEffect } from 'react';
import { CircleMarker, MapContainer, Polyline, Popup, TileLayer, Tooltip, useMap } from 'react-leaflet';

interface PlacesMapProps {
    center: [number, number];
    zoom: number;
    places: Place[];
    layers: Layer[];
    /** Ids of layers to show; undefined shows every layer. */
    visibleLayerIds?: number[];
    /** Ordered places drawn as a connected route. */
    path?: Place[];
    /** Places to highlight (e.g. already added to the route). */
    highlightedIds?: number[];
    renderPopup?: (place: Place) => ReactNode;
    className?: string;
}

function FitToPath({ path }: { path: Place[] }) {
    const map = useMap();
    useEffect(() => {
        if (path.length > 1) {
            map.fitBounds(
                path.map((p) => [p.lat, p.lng]),
                { padding: [40, 40] },
            );
        } else if (path.length === 1) {
            map.setView([path[0].lat, path[0].lng], 15);
        }
    }, [map, path]);
    return null;
}

export function PlacesMap({ center, zoom, places, layers, visibleLayerIds, path, highlightedIds = [], renderPopup, className }: PlacesMapProps) {
    const colorOf = (layerId: number) => layers.find((l) => l.id === layerId)?.color ?? '#64748b';
    const visible = visibleLayerIds ? places.filter((p) => visibleLayerIds.includes(p.layer_id)) : places;
    const stopNumber = (id: number) => (path ? path.findIndex((p) => p.id === id) + 1 : 0);

    return (
        <MapContainer center={center} zoom={zoom} scrollWheelZoom className={className ?? 'h-[480px] w-full rounded-xl'}>
            <TileLayer
                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            />
            {path && path.length > 1 && (
                <Polyline positions={path.map((p) => [p.lat, p.lng])} pathOptions={{ color: '#0f172a', weight: 3, dashArray: '6 8' }} />
            )}
            {path && <FitToPath path={path} />}
            {visible.map((place) => {
                const highlighted = highlightedIds.includes(place.id);
                const n = stopNumber(place.id);
                return (
                    <CircleMarker
                        key={place.id}
                        center={[place.lat, place.lng]}
                        radius={highlighted || n > 0 ? 11 : 8}
                        pathOptions={{
                            color: highlighted ? '#0f172a' : '#ffffff',
                            weight: highlighted ? 3 : 2,
                            fillColor: colorOf(place.layer_id),
                            fillOpacity: 0.9,
                        }}
                    >
                        {n > 0 && (
                            <Tooltip permanent direction="center" className="itinerarium-stop-label">
                                {n}
                            </Tooltip>
                        )}
                        <Popup>
                            <div className="space-y-1">
                                <div className="font-semibold">{place.name}</div>
                                {place.description && <div className="text-xs text-neutral-600">{place.description}</div>}
                                {renderPopup?.(place)}
                            </div>
                        </Popup>
                    </CircleMarker>
                );
            })}
        </MapContainer>
    );
}
