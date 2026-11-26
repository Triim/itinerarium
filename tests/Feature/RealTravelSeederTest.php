<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RealTravelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class RealTravelSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
    }

    private function itinerary(string $title): Itinerary
    {
        return Itinerary::where('title', $title)->sole();
    }

    private function place(string $city, string $name): Place
    {
        return Place::whereRelation('city', 'name', $city)->where('name', $name)->sole();
    }

    /** Stop names in the order the relation returns them. */
    private function stopNames(Itinerary $itinerary): array
    {
        return $itinerary->places()->pluck('places.name')->all();
    }

    /** Row counts that must not change when the seeder runs again. */
    private function counts(): array
    {
        return collect(['users', 'cities', 'places', 'itineraries', 'itinerary_place', 'media'])
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])
            ->all();
    }

    public function test_it_seeds_the_six_real_cities_after_the_samples_with_their_places()
    {
        $author = User::where('email', 'test@example.com')->sole();

        $this->assertSame([
            'Barcelona' => 4,
            'Bangkok' => 3,
            'Madrid' => 22,
            'Berlin' => 28,
            'Paris' => 24,
            'Brussels' => 7,
            'Bruges' => 9,
            'Hamburg' => 21,
        ], City::withCount('places')->orderBy('id')->pluck('places_count', 'name')->all());

        $this->assertSame(111, Place::whereRelation('city', 'id', '>', 2)->count());
        $this->assertSame([$author->id], City::distinct()->pluck('user_id')->all());
    }

    public function test_every_real_place_has_numeric_coordinates_and_a_description()
    {
        $places = Place::whereRelation('city', 'id', '>', 2)->get();

        $this->assertCount(111, $places);
        foreach ($places as $place) {
            $this->assertIsFloat($place->latitude, $place->name);
            $this->assertIsFloat($place->longitude, $place->name);
            $this->assertNotSame('', $place->description, $place->name);
        }

        $this->assertEqualsWithDelta(53.548269, $this->place('Hamburg', 'Chilehaus and Kontorhaus District')->latitude, 0.000001);
    }

    public function test_it_seeds_the_seven_real_itineraries_by_the_test_user()
    {
        $author = User::where('email', 'test@example.com')->sole();

        $this->assertSame([
            'Madrid in a day: Habsburg squares, Egyptian temple and Almodóvar rooftops' => 'Madrid',
            'West Berlin: cinema, Bauhaus and modernism' => 'Berlin',
            'Berlin south to east: Germania, the Stasi and Good Bye, Lenin!' => 'Berlin',
            'Paris on film' => 'Paris',
            'Brussels: a waffle, three statues and three views' => 'Brussels',
            'Bruges, after In Bruges' => 'Bruges',
            'Hamburg on foot in eight hours' => 'Hamburg',
        ], Itinerary::with('city')->where('id', '>', 3)->orderBy('id')->get()
            ->mapWithKeys(fn (Itinerary $itinerary) => [$itinerary->title => $itinerary->city->name])
            ->all());

        $this->assertSame(10, $author->itineraries()->count());
    }

    public function test_real_places_keep_their_film_references()
    {
        $this->assertSame(46, Place::whereRelation('city', 'id', '>', 2)
            ->get()
            ->filter(fn (Place $place) => $place->films !== null && $place->films !== [])
            ->count());

        $this->assertSame(['Christiane F. (1981)'], $this->place('Berlin', 'Bahnhof Zoologischer Garten')->films);
        $this->assertSame(['Emily in Paris'], $this->place('Paris', 'Café de Flore')->films);
        $this->assertSame(['The Day of the Beast', 'Open Your Eyes'], $this->place('Madrid', 'Gran Vía and Callao')->films);
    }

    public function test_the_louvre_is_one_place_with_three_films_and_one_image()
    {
        $louvre = $this->place('Paris', 'Louvre Museum');

        $this->assertSame(['The Dreamers', 'Funny Face', 'The Da Vinci Code'], $louvre->films);
        $this->assertSame(['landmark', 'film', 'architecture', 'experience'], $louvre->themes);
        $this->assertCount(1, $louvre->getMedia('images'));
        $this->assertSame('louvre-the-dreamers.jpg', $louvre->getFirstMedia('images')->file_name);
    }

    public function test_source_categories_are_mapped_to_the_existing_themes()
    {
        $themes = Place::whereRelation('city', 'id', '>', 2)->pluck('themes')->flatten()->unique()->sort()->values()->all();

        $this->assertSame(['architecture', 'experience', 'film', 'history', 'landmark', 'local_food', 'unusual'], $themes);

        // food -> local_food; viewpoint/park/neighbourhood/art/music -> experience.
        $this->assertSame(['architecture', 'unusual', 'local_food'], $this->place('Madrid', 'Mercado de San Miguel')->themes);
        $this->assertSame(['experience'], $this->place('Hamburg', 'Karolinenviertel')->themes);
    }

    public function test_madrid_has_the_seventeen_day_two_stops_and_five_nearby_places_outside_the_route()
    {
        $madrid = $this->itinerary('Madrid in a day: Habsburg squares, Egyptian temple and Almodóvar rooftops');
        $stops = $this->stopNames($madrid);

        $this->assertCount(17, $stops);
        $this->assertSame(['Plaza Mayor', 'Mercado de San Miguel', 'Royal Palace and Almudena Cathedral'], array_slice($stops, 0, 3));
        $this->assertSame('Madrid Atocha Station', end($stops));
        $this->assertSame(range(1, 17), $madrid->places()->pluck('itinerary_place.position')->all());

        $nearby = ['Cerralbo Museum', 'San Antonio de los Alemanes', 'House of the Seven Chimneys', 'Alfonso XII Monument viewpoint', 'Prince of Anglona Garden'];
        foreach ($nearby as $name) {
            $this->assertCount(0, $this->place('Madrid', $name)->itineraries, $name);
        }
    }

    public function test_hamburg_keeps_all_twenty_one_stops_in_order()
    {
        $this->assertSame([
            'Hamburg Central Station', 'Rathausmarkt and Hamburg City Hall', 'Nikolaifleet', 'Deichstraße',
            'Chilehaus and Kontorhaus District', 'Speicherstadt', 'Magellan Terraces', 'Elbphilharmonie',
            'Traditionsschiffhafen', 'St. Pauli Landungsbrücken', 'Old Elbe Tunnel', "St. Michael's Church (Michel)",
            'Zum Goldenen Handschuh', 'Große Freiheit', 'Beatles-Platz', 'Feldstraße Bunker', 'Karolinenviertel',
            'Schanzenviertel', 'Dockland', 'Große Elbstraße', 'Hamburg Fish Market',
        ], $this->stopNames($this->itinerary('Hamburg on foot in eight hours')));
    }

    public function test_every_real_itinerary_follows_the_bundled_stop_order()
    {
        $data = json_decode(file_get_contents(database_path('data/real-travel/places.json')), true);
        $names = array_column($data['places'], 'name', 'key');
        $itineraries = json_decode(file_get_contents(database_path('data/real-travel/itineraries.json')), true)['itineraries'];

        $this->assertCount(7, $itineraries);
        foreach ($itineraries as $row) {
            $expected = array_map(fn (string $key) => $names[$key], $row['stops']);
            $this->assertSame($expected, $this->stopNames($this->itinerary($row['title'])), $row['title']);
        }
    }

    public function test_the_two_berlin_routes_share_six_places_without_duplicating_them()
    {
        $west = $this->itinerary('West Berlin: cinema, Bauhaus and modernism');
        $east = $this->itinerary('Berlin south to east: Germania, the Stasi and Good Bye, Lenin!');

        $this->assertCount(17, $west->places);
        $this->assertCount(17, $east->places);

        $shared = $west->places->pluck('id')->intersect($east->places->pluck('id'));
        $this->assertCount(6, $shared);
        $this->assertSame(28, City::where('name', 'Berlin')->sole()->places()->count());

        // The east route starts by walking the end of the west route in reverse.
        $this->assertSame('Pallasbunker and Pallasseum', $this->stopNames($west)[16]);
        $this->assertSame('Pallasbunker and Pallasseum', $this->stopNames($east)[0]);
        $this->assertSame(1, Place::where('name', 'Pallasbunker and Pallasseum')->count());
    }

    public function test_the_bundled_images_are_attached_to_the_right_places_with_conversions()
    {
        $this->assertSame(30, Media::where('collection_name', 'images')->count());
        $this->assertSame(30, Media::distinct()->count('model_id'));

        $expected = [
            ['Paris', 'Café des Deux Moulins', 'cafe-des-deux-moulins.jpg'],
            ['Paris', 'Père Lachaise Cemetery', 'pere-lachaise.png'],
            ['Brussels', 'Grand-Place', 'grand-place.jpg'],
            ['Bruges', 'Groeningemuseum', 'groeningemuseum.jpg'],
        ];
        foreach ($expected as [$city, $name, $file]) {
            $media = $this->place($city, $name)->getFirstMedia('images');

            $this->assertSame($file, $media->file_name, $name);
            Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
            foreach (['thumbnail', 'details'] as $conversion) {
                $this->assertTrue($media->hasGeneratedConversion($conversion), "{$name} {$conversion}");
                Storage::disk('public')->assertExists($media->getPathRelativeToRoot($conversion));
            }
        }

        $this->assertNull($this->place('Hamburg', 'Elbphilharmonie')->getFirstMedia('images'));
    }

    public function test_running_the_real_travel_seeder_again_adds_nothing()
    {
        $before = $this->counts();
        $hamburg = $this->stopNames($this->itinerary('Hamburg on foot in eight hours'));

        $this->seed(RealTravelSeeder::class);

        $this->assertSame($before, $this->counts());
        $this->assertSame($hamburg, $this->stopNames($this->itinerary('Hamburg on foot in eight hours')));
    }

    public function test_running_it_again_keeps_changes_made_to_seeded_records()
    {
        $louvre = $this->place('Paris', 'Louvre Museum');
        $louvre->update(['description' => 'Edited by a user.']);

        $this->seed(RealTravelSeeder::class);

        $this->assertSame('Edited by a user.', $louvre->fresh()->description);
    }

    public function test_the_welcome_page_shows_the_newest_real_itineraries()
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->has('itineraries', 3)
                ->where('itineraries.0.title', 'Hamburg on foot in eight hours')
                ->where('itineraries.0.places_count', 21)
                ->where('itineraries.0.user.name', 'Test User')
                ->where('itineraries.1.title', 'Bruges, after In Bruges')
                ->where('itineraries.2.title', 'Brussels: a waffle, three statues and three views'));
    }

    public function test_public_city_place_and_itinerary_pages_show_the_real_data()
    {
        $berlin = City::where('name', 'Berlin')->sole();
        $this->get(route('cities.show', $berlin))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cities/show')
                ->has('itineraries', 2));

        $louvre = $this->place('Paris', 'Louvre Museum');
        $this->get(route('places.show', $louvre))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('places/show')
                ->where('place.films', ['The Dreamers', 'Funny Face', 'The Da Vinci Code'])
                ->has('place.themes', 4)
                ->where('place.image_url', fn (string $url) => str_contains($url, 'louvre-the-dreamers'))
                ->has('itineraries', 1));

        $paris = $this->itinerary('Paris on film');
        $this->get(route('itineraries.show', $paris))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('itineraries/show')
                ->has('itinerary.places', 24)
                ->where('itinerary.places.0.name', 'Louvre Museum'));

        $this->get(route('places.index'))->assertOk();
        $this->get(route('itineraries.index'))->assertOk();
    }
}
