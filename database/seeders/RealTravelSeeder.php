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
 * are synced to the bundled order. The photographs and their credits are listed
 * in images.json; `php artisan real-travel:sync-images` updates an existing
 * installation whose places already have older bundled images.
 */
class RealTravelSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('email', 'test@example.com')->firstOrFail();
        $data = RealTravelImages::readJson('places.json');
        $images = new RealTravelImages;
        $bundled = $images->images();

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

            if (isset($bundled[$row['key']]) && ! $place->hasMedia('images')) {
                $images->attach($place, $bundled[$row['key']]);
            }

            $placeIds[$row['key']] = $place->id;
        }

        foreach (RealTravelImages::readJson('itineraries.json')['itineraries'] as $row) {
            // Prepared routes are public content; the status is only set when the itinerary is created.
            $itinerary = Itinerary::firstOrCreate([
                'title' => $row['title'],
                'city_id' => $cities[$row['city']]->id,
                'user_id' => $author->id,
            ], ['status' => Itinerary::PUBLISHED]);

            $itinerary->syncStops(array_map(fn (string $key) => $placeIds[$key], $row['stops']));
        }
    }
}
