import { type ImageCredit, PhotoCredit } from '@/components/photo-credit';
import { Link } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { useState } from 'react';

// A city or itinerary cover: the photo of one of its places, chosen on the server.
export type Cover = {
    url: string;
    credit: ImageCredit | null;
    place: { id: number; name: string };
} | null;

type Props = {
    cover: Cover;
    /** Sets the aspect ratio and corners; the frame keeps its size while the photo loads. */
    className: string;
    /** Card photos are decorative, since the card's title names the link. */
    alt?: string;
    lazy?: boolean;
    /** A short "Photo: author, licence" note over the photo, for cards; the linked page has the full credit. */
    shortCredit?: boolean;
};

export default function CoverImage({ cover, className, alt = '', lazy = false, shortCredit = false }: Props) {
    // A missing or broken file shows the same placeholder as a city or itinerary without a photo.
    const [failed, setFailed] = useState(false);
    const showPhoto = cover !== null && !failed;

    return (
        <div className={`relative overflow-hidden bg-stone-200 ${className}`}>
            {showPhoto ? (
                <>
                    <img
                        src={cover.url}
                        alt={alt}
                        loading={lazy ? 'lazy' : undefined}
                        decoding="async"
                        onError={() => setFailed(true)}
                        className="absolute inset-0 size-full object-cover transition-transform duration-300 motion-safe:group-hover:scale-[1.03]"
                    />
                    {shortCredit && cover.credit && (
                        <span className="absolute right-0 bottom-0 max-w-full truncate rounded-tl-md bg-stone-950/60 px-1.5 py-0.5 text-[10px] leading-tight text-white">
                            Photo: {cover.credit.author}, {cover.credit.license}
                        </span>
                    )}
                </>
            ) : (
                <div aria-hidden="true" className="absolute inset-0 flex items-center justify-center text-stone-400">
                    <MapPin className="size-7" strokeWidth={1.5} />
                </div>
            )}
        </div>
    );
}

// The page header photo, with the place it shows and its full credit. Pages without a cover show none.
export function CoverHero({ cover }: { cover: NonNullable<Cover> }) {
    return (
        <figure className="mt-6">
            <CoverImage cover={cover} alt={`Photo of ${cover.place.name}`} className="aspect-[4/3] rounded-xl sm:aspect-[21/9]" />
            <figcaption className="mt-2 text-xs leading-relaxed break-words text-stone-500">
                Pictured:{' '}
                <Link
                    href={route('places.show', cover.place.id)}
                    className="underline underline-offset-2 hover:text-stone-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
                >
                    {cover.place.name}
                </Link>
                .{cover.credit && ' '}
                {cover.credit && <PhotoCredit credit={cover.credit} cropped />}
            </figcaption>
        </figure>
    );
}
