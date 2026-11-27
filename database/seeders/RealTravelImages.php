<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Place;
use App\Models\User;
use Illuminate\Support\Arr;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The bundled Place photographs listed in database/data/real-travel/images.json.
 *
 * Media attached from here carry a "seed_image" custom property with the bundled path and its
 * SHA-256, plus the photo credit, so they can be recognised later without touching uploads.
 * Media from the previous bundle are recognised by the file name and size listed under "retired".
 */
class RealTravelImages
{
    /** Credit fields copied to the media and shown on the public Place page. */
    public const CREDIT = ['title', 'author', 'source_url', 'license', 'license_url', 'attribution', 'modifications'];

    private array $manifest;

    public function __construct()
    {
        $this->manifest = self::readJson('images.json');
    }

    public static function readJson(string $file): array
    {
        return json_decode(file_get_contents(database_path("data/real-travel/{$file}")), true, flags: JSON_THROW_ON_ERROR);
    }

    /** Image entries keyed by Place key. */
    public function images(): array
    {
        return array_column($this->manifest['images'], null, 'place');
    }

    /** Previously bundled images, keyed by Place key. */
    public function retired(): array
    {
        return array_column($this->manifest['retired'], null, 'place');
    }

    /**
     * The seeded Places by key, or null where a Place no longer exists, matched the way the seeder creates them.
     */
    public function places(User $author): array
    {
        $data = self::readJson('places.json');
        $cityIds = City::where('user_id', $author->id)
            ->whereIn('name', array_column($data['cities'], 'name'))
            ->pluck('id', 'name');
        $cityNames = array_column($data['cities'], 'name', 'key');

        $places = [];
        foreach ($data['places'] as $row) {
            $cityId = $cityIds[$cityNames[$row['city']]] ?? null;
            $places[$row['key']] = $cityId === null ? null : Place::where('city_id', $cityId)->where('name', $row['name'])->first();
        }

        return $places;
    }

    /**
     * The images are bundled in one tar archive, read through PHP's built-in phar:// stream.
     */
    public function attach(Place $place, array $image): Media
    {
        $stream = fopen('phar://'.database_path("data/real-travel/images.tar/{$image['file']}"), 'rb');

        $media = $place->addMediaFromStream($stream)
            ->usingName(pathinfo($image['file'], PATHINFO_FILENAME))
            ->usingFileName(basename($image['file']))
            ->withCustomProperties([
                'seed_image' => Arr::only($image, ['file', 'sha256']),
                'credit' => Arr::only($image, self::CREDIT),
            ])
            ->toMediaCollection('images');

        fclose($stream);

        return $media;
    }

    /** Whether the media is exactly this bundled image. */
    public function isCurrent(Media $media, array $image): bool
    {
        return $media->getCustomProperty('seed_image') === Arr::only($image, ['file', 'sha256']);
    }

    /**
     * Whether the media came from a bundle: the current one, or the retired one for this Place.
     * Anything else, such as an uploaded image, is never replaced.
     */
    public function isSeedManaged(Media $media, ?array $retired): bool
    {
        if ($media->hasCustomProperty('seed_image')) {
            return true;
        }

        return $retired !== null
            && $media->file_name === $retired['file_name']
            && (int) $media->size === $retired['size'];
    }
}
