<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Seeder;

/** An editor account with sample author routes and editorial collections. */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $editor = User::firstOrCreate(
            ['email' => 'editor@itinerarium.test'],
            ['name' => 'Itinerarium Editors', 'password' => 'password', 'email_verified_at' => now()],
        );

        $routes = [
            ['saint-petersburg', 'Classic Petersburg in a day', 'The main museums and cathedrals of the centre.', ['The Hermitage', 'Palace Square', 'Church of the Savior on Spilled Blood', "St. Isaac's Cathedral", 'Palace Bridge opening']],
            ['saint-petersburg', 'Underground Petersburg', 'Boiler rooms, art centres and doughnuts.', ['Kamchatka Boiler Room', 'Pyshechnaya on Bolshaya Konyushennaya', 'Pushkinskaya 10']],
            ['rome', 'Rome on film', 'In the footsteps of Fellini and "Roman Holiday".', ['Mouth of Truth', 'Trevi Fountain', 'Spanish Steps']],
            ['rome', 'Rome until sunset', 'Antiquity by day, a panorama at dusk.', ['Colosseum', 'Testaccio Market', 'Aventine Keyhole', 'Sunset on the Janiculum']],
            ['tbilisi', 'Tbilisi for first-timers', 'Old Town and the baths.', ['Bridge of Peace', 'Narikala Fortress', 'Abanotubani sulphur baths', 'Dry Bridge flea market']],
        ];

        $created = [];
        foreach ($routes as [$citySlug, $title, $description, $stops]) {
            $city = City::where('slug', $citySlug)->firstOrFail();
            $itinerary = $editor->itineraries()->updateOrCreate(
                ['title' => $title],
                ['city_id' => $city->id, 'description' => $description, 'is_published' => true],
            );
            $ids = collect($stops)->map(fn ($name) => Place::where('city_id', $city->id)->where('name', $name)->value('id'))->filter()->all();
            $itinerary->syncStops($ids);
            $created[$title] = $itinerary->id;
        }

        $selections = [
            ['first-time', 'First time in town', null, 'Routes to start getting to know a city.', ['Classic Petersburg in a day', 'Tbilisi for first-timers', 'Rome until sunset']],
            ['cinema', 'Film tourism', null, 'Walk through the scenes of your favourite films.', ['Rome on film']],
            ['petersburg-locals', 'Petersburg like a local', 'saint-petersburg', 'No queues, no tour buses.', ['Underground Petersburg']],
        ];

        foreach ($selections as [$slug, $title, $citySlug, $description, $titles]) {
            $selection = $editor->selections()->updateOrCreate(
                ['slug' => $slug],
                ['title' => $title, 'description' => $description, 'city_id' => $citySlug ? City::where('slug', $citySlug)->value('id') : null],
            );
            $selection->itineraries()->sync(
                collect($titles)->mapWithKeys(fn ($t, $i) => [$created[$t] => ['position' => $i + 1]])->all()
            );
        }
    }
}
