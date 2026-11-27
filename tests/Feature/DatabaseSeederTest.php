<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RealTravelImages;
use Database\Seeders\RealTravelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * The complete DatabaseSeeder: the administrator, the factory-made samples and the real travel data.
 * Seeding attaches and converts 100 photographs, so each test seeds once and checks one area.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_it_creates_the_administrator_and_the_sample_data()
    {
        // The lecturer's required login.
        $admin = User::where('is_admin', true)->sole();
        $this->assertSame('admin@example.com', $admin->email);
        $this->assertTrue(Hash::check('password', $admin->password));

        $author = User::where('email', 'test@example.com')->sole();
        $this->assertFalse($author->is_admin);
        $this->assertSame(2, User::count());

        // Every sample record comes from a factory, without extra users or cities.
        $this->assertSame([
            'Barcelona' => ['Sagrada Família', 'Park Güell', 'Casa Batlló', 'Barcelona Cathedral'],
            'Bangkok' => ['Grand Palace', 'Wat Pho', 'Wat Arun'],
        ], City::with('places')->orderBy('id')->take(2)->get()
            ->mapWithKeys(fn (City $city) => [$city->name => $city->places->sortBy('id')->pluck('name')->all()])
            ->all());
        $this->assertSame([
            'Sample: A Weekend in Barcelona' => ['Sagrada Família', 'Park Güell', 'Casa Batlló'],
            'Sample: Barcelona on Foot' => ['Casa Batlló', 'Barcelona Cathedral'],
            'Sample: First Day in Bangkok' => ['Grand Palace', 'Wat Pho', 'Wat Arun'],
        ], Itinerary::with('places')->orderBy('id')->take(3)->get()
            ->mapWithKeys(fn (Itinerary $itinerary) => [$itinerary->title => $itinerary->places->sortBy('id')->pluck('name')->all()])
            ->all());

        $sagrada = Place::where('name', 'Sagrada Família')->sole();
        $this->assertSame(41.40369, $sagrada->latitude);
        $this->assertSame(2.17433, $sagrada->longitude);

        // The whole dataset: 2 sample and 6 real cities, 7 + 111 places, 3 + 7 itineraries.
        $this->assertSame([$author->id], City::distinct()->pluck('user_id')->all());
        $this->assertSame([$author->id], Itinerary::distinct()->pluck('user_id')->all());
        $this->assertDatabaseCount('cities', 8);
        $this->assertDatabaseCount('places', 118);
        $this->assertDatabaseCount('itineraries', 10);
        $this->assertDatabaseCount('itinerary_place', 8 + 112);
        $this->assertSame(10, Itinerary::published()->count());

        foreach (Itinerary::with('places')->get() as $itinerary) {
            $this->assertSame([$itinerary->city_id], $itinerary->places->pluck('city_id')->unique()->values()->all(), $itinerary->title);
        }
    }

    public function test_it_creates_the_real_travel_places_and_itineraries()
    {
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

        $realPlaces = Place::whereRelation('city', 'id', '>', 2)->get();
        $this->assertCount(111, $realPlaces);
        foreach ($realPlaces as $place) {
            $this->assertIsFloat($place->latitude, $place->name);
            $this->assertIsFloat($place->longitude, $place->name);
            $this->assertNotSame('', $place->description, $place->name);
        }
        $this->assertSame(46, $realPlaces->filter(fn (Place $place) => $place->films !== null && $place->films !== [])->count());

        // Source categories are mapped to the existing themes: food -> local_food, viewpoint/park/... -> experience.
        $this->assertSame(
            ['architecture', 'experience', 'film', 'history', 'landmark', 'local_food', 'unusual'],
            $realPlaces->pluck('themes')->flatten()->unique()->sort()->values()->all(),
        );
        $this->assertSame(['architecture', 'unusual', 'local_food'], $this->place('Madrid', 'Mercado de San Miguel')->themes);
        $this->assertSame(['experience'], $this->place('Hamburg', 'Karolinenviertel')->themes);

        $louvre = $this->place('Paris', 'Louvre Museum');
        $this->assertSame(['The Dreamers', 'Funny Face', 'The Da Vinci Code'], $louvre->films);
        $this->assertSame(['landmark', 'film', 'architecture', 'experience'], $louvre->themes);
        $this->assertSame(['The Day of the Beast', 'Open Your Eyes'], $this->place('Madrid', 'Gran Vía and Callao')->films);

        // Every real itinerary follows the bundled stop order, numbered from 1.
        $names = array_column(RealTravelImages::readJson('places.json')['places'], 'name', 'key');
        $rows = RealTravelImages::readJson('itineraries.json')['itineraries'];
        $this->assertCount(7, $rows);
        foreach ($rows as $row) {
            $itinerary = $this->itinerary($row['title']);

            $this->assertSame(array_map(fn (string $key) => $names[$key], $row['stops']), $this->stopNames($itinerary), $row['title']);
            $this->assertSame(range(1, count($row['stops'])), $itinerary->places()->pluck('itinerary_place.position')->all(), $row['title']);
        }
        $this->assertCount(21, $this->stopNames($this->itinerary('Hamburg on foot in eight hours')));

        // Madrid also has five nearby places that are on no route.
        foreach (['Cerralbo Museum', 'San Antonio de los Alemanes', 'House of the Seven Chimneys', 'Alfonso XII Monument viewpoint', 'Prince of Anglona Garden'] as $name) {
            $this->assertCount(0, $this->place('Madrid', $name)->itineraries, $name);
        }

        // The two Berlin routes share six places instead of duplicating them; the east route starts where the west one ends.
        $west = $this->itinerary('West Berlin: cinema, Bauhaus and modernism');
        $east = $this->itinerary('Berlin south to east: Germania, the Stasi and Good Bye, Lenin!');
        $this->assertCount(6, $west->places->pluck('id')->intersect($east->places->pluck('id')));
        $this->assertSame('Pallasbunker and Pallasseum', $this->stopNames($west)[16]);
        $this->assertSame('Pallasbunker and Pallasseum', $this->stopNames($east)[0]);
    }

    public function test_every_bundled_photograph_is_attached_to_its_place_with_its_credit_and_conversions()
    {
        $images = (new RealTravelImages)->images();
        $names = array_column(RealTravelImages::readJson('places.json')['places'], 'name', 'key');
        $disk = Storage::disk('public');

        // The 3 sample photographs are checked below; the rest are the bundled real travel photographs.
        $samples = ['Sagrada Família', 'Casa Batlló', 'Grand Palace'];
        $media = Media::with('model')->get()->reject(fn (Media $item) => in_array($item->model->name, $samples, true));
        $this->assertCount(97, $images);
        $this->assertCount(97, $media);
        $this->assertSame(97, $media->pluck('model_id')->unique()->count());

        foreach ($media as $item) {
            $image = $images[array_search($item->model->name, $names, true)];

            $this->assertSame(basename($image['file']), $item->file_name);
            $this->assertSame(Arr::only($image, ['file', 'sha256']), $item->getCustomProperty('seed_image'));
            $this->assertSame(Arr::only($image, RealTravelImages::CREDIT), $item->getCustomProperty('credit'));
            $this->assertSame($image['sha256'], hash('sha256', $disk->get($item->getPathRelativeToRoot())));
            foreach (['thumbnail', 'details', 'cover'] as $conversion) {
                $this->assertTrue($item->hasGeneratedConversion($conversion), "{$item->file_name} {$conversion}");
                $disk->assertExists($item->getPathRelativeToRoot($conversion));
            }
        }

        $sampleImages = array_column(json_decode(file_get_contents(database_path('data/samples/images.json')), true)['images'], null, 'place');
        foreach ($samples as $name) {
            $item = Place::where('name', $name)->sole()->getFirstMedia('images');
            $this->assertSame(Arr::only($sampleImages[$name], RealTravelImages::CREDIT), $item->getCustomProperty('credit'));
            $this->assertTrue($item->hasGeneratedConversion('cover'), $name);
        }

        // Without a verified photograph a place has no image, rather than an image from the retired bundle.
        foreach ([['Paris', 'Residence Rose Garden'], ['Paris', "L'Écoute sculpture"], ['Bruges', "'Let it go' stop"], ['Brussels', 'Waffle breakfast stop'], ['Berlin', 'LEGO Store Berlin']] as [$city, $name]) {
            $this->assertNull($this->place($city, $name)->getFirstMedia('images'), $name);
        }
        $this->assertSame(0, Media::whereIn('file_name', array_column((new RealTravelImages)->retired(), 'file_name'))->count());
    }

    public function test_running_the_real_travel_seeder_again_adds_nothing_and_keeps_edits()
    {
        $before = $this->counts();
        $hamburg = $this->stopNames($this->itinerary('Hamburg on foot in eight hours'));
        $louvre = $this->place('Paris', 'Louvre Museum');
        $louvre->update(['description' => 'Edited by a user.']);
        $mediaIds = Media::orderBy('id')->pluck('id')->all();

        $this->seed(RealTravelSeeder::class);

        $this->assertSame($before, $this->counts());
        // Places that already have an image keep it, rather than getting a fresh copy.
        $this->assertSame($mediaIds, Media::orderBy('id')->pluck('id')->all());
        $this->assertSame($hamburg, $this->stopNames($this->itinerary('Hamburg on foot in eight hours')));
        $this->assertSame('Edited by a user.', $louvre->fresh()->description);
    }

    public function test_the_seeded_administrator_can_log_in_and_manage_other_users_records()
    {
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        $admin = User::where('email', 'admin@example.com')->sole();
        $this->assertAuthenticatedAs($admin);

        $city = City::where('name', 'Barcelona')->sole();
        $place = Place::where('name', 'Sagrada Família')->sole();
        $itinerary = Itinerary::where('title', 'Sample: Barcelona on Foot')->sole();
        $this->assertNotSame($admin->id, $itinerary->user_id);

        $this->get(route('userzone.cities.index'))->assertOk();
        $this->get(route('userzone.cities.edit', $city))->assertOk();
        $this->get(route('userzone.places.index'))->assertOk();
        $this->get(route('userzone.places.edit', $place))->assertOk();
        $this->get(route('userzone.itineraries.show', $itinerary))->assertRedirect(route('itineraries.show', $itinerary));
        $this->get(route('userzone.itineraries.edit', $itinerary))->assertOk();

        $this->put(route('userzone.itineraries.update', $itinerary), [
            'title' => 'Barcelona on Foot, revised',
            'city_id' => $city->id,
            'place_ids' => [$place->id],
        ])->assertRedirect(route('userzone.itineraries.index'));

        $this->assertSame('Barcelona on Foot, revised', $itinerary->fresh()->title);
        $this->assertSame($itinerary->user_id, $itinerary->fresh()->user_id);
    }

    public function test_public_pages_show_the_seeded_data()
    {
        // The real routes are seeded after the samples, so they are the newest.
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

        $this->get(route('cities.show', City::where('name', 'Berlin')->sole()))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('itineraries', 2));

        $this->get(route('places.show', $this->place('Paris', 'Louvre Museum')))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('place.films', ['The Dreamers', 'Funny Face', 'The Da Vinci Code'])
                ->has('place.themes', 4)
                ->where('place.image_url', fn (string $url) => str_contains($url, 'it-080-louvre'))
                ->where('place.image_credit.license', 'CC0')
                ->has('itineraries', 1));

        $this->get(route('itineraries.show', $this->itinerary('Paris on film')))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('itinerary.places', 24)
                ->where('itinerary.places.0.name', 'Louvre Museum'));

        $this->get(route('places.index'))->assertOk();
    }

    public function test_every_city_and_itinerary_has_a_photo_cover_from_one_of_its_places()
    {
        // Cities: the first photographed landmark by name, otherwise the first photographed place.
        $this->assertSame([
            'Bangkok' => 'Grand Palace',
            'Barcelona' => 'Casa Batlló',
            'Berlin' => 'Berlin Wall Memorial (Bernauer Straße)',
            'Bruges' => 'Belfry of Bruges',
            'Brussels' => 'Grand-Place',
            'Hamburg' => 'Elbphilharmonie',
            'Madrid' => 'Gran Vía and Callao',
            'Paris' => 'Arc de Triomphe',
        ], $this->coverPlaces(route('cities.index'), 'cities', 'name'));

        // Itineraries: the first stop with a photo, in stop order.
        $this->assertSame([
            'Berlin south to east: Germania, the Stasi and Good Bye, Lenin!' => 'Pallasbunker and Pallasseum',
            'Bruges, after In Bruges' => 'Belfry of Bruges',
            'Brussels: a waffle, three statues and three views' => 'Zinneke Pis',
            'Hamburg on foot in eight hours' => 'Hamburg Central Station',
            'Madrid in a day: Habsburg squares, Egyptian temple and Almodóvar rooftops' => 'Plaza Mayor',
            'Paris on film' => 'Louvre Museum',
            'Sample: A Weekend in Barcelona' => 'Sagrada Família',
            'Sample: Barcelona on Foot' => 'Casa Batlló',
            'Sample: First Day in Bangkok' => 'Grand Palace',
            'West Berlin: cinema, Bauhaus and modernism' => 'Bahnhof Zoologischer Garten',
        ], $this->coverPlaces(route('itineraries.index'), 'itineraries', 'title'));
    }

    /** The name of the place each card's cover shows, keyed by the card's name or title. */
    private function coverPlaces(string $url, string $prop, string $key): array
    {
        $cards = $this->get($url)->assertOk()->viewData('page')['props'][$prop];

        return collect($cards)->mapWithKeys(fn (array $card) => [$card[$key] => $card['cover']['place']['name']])->all();
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
}
