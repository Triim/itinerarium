<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * Real travel routes from the author's own trips, read from database/data/real-travel.
 *
 * Running it again adds nothing new: cities, places and itineraries are matched
 * by name or title, places that already have an image keep it, and the stops
 * are synced to the bundled order.
 */
class RealTravelSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('email', 'test@example.com')->firstOrFail();
        $data = $this->readJson('places.json');

        $cities = [];
        foreach ($data['cities'] as $city) {
            $cities[$city['key']] = City::firstOrCreate(['name' => $city['name'], 'user_id' => $author->id]);
        }

        $placeIds = [];
        foreach ($data['places'] as $row) {
            $place = Place::firstOrCreate(
                ['city_id' => $cities[$row['city']]->id, 'name' => $row['name']],
                Arr::only($row, ['description', 'latitude', 'longitude', 'themes', 'films']),
            );

            if ($row['image'] !== null && ! $place->hasMedia('images')) {
                $this->attachImage($place, $row['image']);
            }

            $placeIds[$row['key']] = $place->id;
        }

        foreach ($this->readJson('itineraries.json')['itineraries'] as $row) {
            $itinerary = Itinerary::firstOrCreate([
                'title' => $row['title'],
                'city_id' => $cities[$row['city']]->id,
                'user_id' => $author->id,
            ]);

            $itinerary->syncStops(array_map(fn (string $key) => $placeIds[$key], $row['stops']));
        }
    }

    private function readJson(string $file): array
    {
        return json_decode(file_get_contents(database_path("data/real-travel/{$file}")), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * The images are bundled in one tar archive, read through PHP's built-in phar:// stream.
     */
    private function attachImage(Place $place, string $image): void
    {
        $stream = fopen('phar://'.database_path("data/real-travel/images.tar/{$image}"), 'rb');

        $place->addMediaFromStream($stream)
            ->usingName(pathinfo($image, PATHINFO_FILENAME))
            ->usingFileName(basename($image))
            ->toMediaCollection('images');

        fclose($stream);
    }
}
