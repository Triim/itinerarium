<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPlaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_see_all_places_grouped_by_city()
    {
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $bangkok = City::factory()->create(['name' => 'Bangkok']);
        City::factory()->create(['name' => 'Athens']);
        $sagradaFamilia = Place::factory()->for($barcelona)->create(['name' => 'Sagrada Família']);
        Place::factory()->for($barcelona)->create(['name' => 'Casa Batlló']);
        Place::factory()->for($bangkok)->create(['name' => 'Wat Pho']);
        Itinerary::factory()->count(2)->for($barcelona)->hasAttached($sagradaFamilia)->create();

        $this->get('/places')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('places/index')
                ->has('cities', 2)
                ->where('cities.0.name', 'Bangkok')
                ->has('cities.0.places', 1)
                ->where('cities.0.places.0.name', 'Wat Pho')
                ->where('cities.1.name', 'Barcelona')
                ->has('cities.1.places', 2)
                ->where('cities.1.places.0.name', 'Casa Batlló')
                ->where('cities.1.places.0.itineraries_count', 0)
                ->where('cities.1.places.1.name', 'Sagrada Família')
                ->where('cities.1.places.1.itineraries_count', 2));
    }

    public function test_places_page_renders_when_there_are_no_places()
    {
        $this->get('/places')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('places/index')
                ->has('cities', 0));
    }

    public function test_guests_can_see_a_place_with_only_its_own_itineraries()
    {
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $place = Place::factory()->for($barcelona)->create([
            'name' => 'Casa Batlló',
            'description' => 'House remodelled by Antoni Gaudí.',
        ]);
        $onFoot = Itinerary::factory()->for($barcelona)->hasAttached($place)->create(['title' => 'Barcelona on Foot']);
        $weekend = Itinerary::factory()->for($barcelona)->hasAttached($place)->create(['title' => 'A Weekend in Barcelona']);
        Itinerary::factory()->for($barcelona)->create(['title' => 'Another Itinerary']);

        $this->get("/places/{$place->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('places/show')
                ->where('place.name', 'Casa Batlló')
                ->where('place.description', 'House remodelled by Antoni Gaudí.')
                ->where('city.id', $barcelona->id)
                ->where('city.name', 'Barcelona')
                ->has('itineraries', 2)
                ->where('itineraries.0.id', $weekend->id)
                ->where('itineraries.0.title', 'A Weekend in Barcelona')
                ->where('itineraries.0.user.name', $weekend->user->name)
                ->where('itineraries.1.id', $onFoot->id));
    }

    public function test_place_page_renders_when_the_place_has_no_itineraries()
    {
        $place = Place::factory()->create();

        $this->get("/places/{$place->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('places/show')
                ->has('itineraries', 0));
    }

    public function test_place_page_returns_not_found_for_a_missing_place()
    {
        $this->get('/places/999')->assertNotFound();
    }

    public function test_itinerary_page_shows_only_its_own_places()
    {
        $barcelona = City::factory()->create();
        $sagradaFamilia = Place::factory()->for($barcelona)->create(['name' => 'Sagrada Família']);
        $casaBatllo = Place::factory()->for($barcelona)->create(['name' => 'Casa Batlló']);
        Place::factory()->for($barcelona)->create(['name' => 'Park Güell']);
        $itinerary = Itinerary::factory()->for($barcelona)->create();
        $itinerary->places()->attach([$sagradaFamilia->id, $casaBatllo->id]);

        $this->get("/itineraries/{$itinerary->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('itineraries/show')
                ->where('itinerary.title', $itinerary->title)
                ->where('itinerary.city.name', $barcelona->name)
                ->where('itinerary.user.name', $itinerary->user->name)
                ->has('itinerary.places', 2)
                ->where('itinerary.places.0.id', $casaBatllo->id)
                ->where('itinerary.places.0.name', 'Casa Batlló')
                ->where('itinerary.places.1.id', $sagradaFamilia->id));
    }

    public function test_itinerary_page_shows_places_in_stop_order()
    {
        $barcelona = City::factory()->create();
        $sagradaFamilia = Place::factory()->for($barcelona)->create(['name' => 'Sagrada Família']);
        $casaBatllo = Place::factory()->for($barcelona)->create(['name' => 'Casa Batlló']);
        $parkGuell = Place::factory()->for($barcelona)->create(['name' => 'Park Güell']);
        $itinerary = Itinerary::factory()->for($barcelona)->create();
        $itinerary->syncStops([$sagradaFamilia->id, $parkGuell->id, $casaBatllo->id]);

        $this->get("/itineraries/{$itinerary->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('itineraries/show')
                ->has('itinerary.places', 3)
                ->where('itinerary.places.0.name', 'Sagrada Família')
                ->where('itinerary.places.1.name', 'Park Güell')
                ->where('itinerary.places.2.name', 'Casa Batlló'));
    }

    public function test_guests_can_see_itineraries_with_their_city_author_and_place_count()
    {
        $berlin = City::factory()->create(['name' => 'Berlin']);
        $hamburg = City::factory()->create(['name' => 'Hamburg']);
        $ada = User::factory()->create(['name' => 'Ada']);
        $westBerlin = Itinerary::factory()->for($berlin)->for($ada)
            ->hasAttached(Place::factory()->count(3)->for($berlin))
            ->create(['title' => 'West Berlin: cinema, Bauhaus and modernism']);
        $southToEast = Itinerary::factory()->for($berlin)
            ->create(['title' => 'Berlin south to east: Germania, the Stasi and Good Bye, Lenin!']);
        $onFoot = Itinerary::factory()->for($hamburg)
            ->hasAttached(Place::factory()->for($hamburg))
            ->create(['title' => 'Hamburg on foot in eight hours']);

        $this->get('/itineraries')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('itineraries/index')
                ->has('itineraries', 3)
                ->where('itineraries.0.id', $southToEast->id)
                ->where('itineraries.0.places_count', 0)
                ->where('itineraries.0.city.name', 'Berlin')
                ->where('itineraries.0.user.name', $southToEast->user->name)
                ->where('itineraries.1.id', $onFoot->id)
                ->where('itineraries.1.places_count', 1)
                ->where('itineraries.1.city.name', 'Hamburg')
                ->where('itineraries.2.id', $westBerlin->id)
                ->where('itineraries.2.title', 'West Berlin: cinema, Bauhaus and modernism')
                ->where('itineraries.2.places_count', 3)
                ->where('itineraries.2.city.name', 'Berlin')
                ->where('itineraries.2.user.name', 'Ada'));
    }

    public function test_itineraries_page_renders_when_there_are_no_itineraries()
    {
        $this->get('/itineraries')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('itineraries/index')
                ->has('itineraries', 0));
    }

    public function test_itinerary_page_shares_place_descriptions_themes_films_and_thumbnails()
    {
        Storage::fake('public');
        $paris = City::factory()->create(['name' => 'Paris']);
        $louvre = Place::factory()->for($paris)->create([
            'name' => 'Louvre Museum',
            'description' => 'Former royal palace, now the largest art museum in the world.',
            'themes' => ['film', 'landmark'],
            'films' => ['The Dreamers', 'Funny Face', 'The Da Vinci Code'],
        ]);
        $louvre->addMedia(UploadedFile::fake()->image('louvre.jpg', 800, 600))->toMediaCollection('images');
        $bareStop = Place::factory()->for($paris)->create(['name' => 'Bare stop', 'description' => 'Short.']);
        $itinerary = Itinerary::factory()->for($paris)->create(['title' => 'Paris on film']);
        $itinerary->syncStops([$louvre->id, $bareStop->id]);

        $thumbnailUrl = $louvre->getFirstMedia('images')->getUrl('thumbnail');
        $this->assertStringContainsString('/conversions/', $thumbnailUrl);

        $this->get("/itineraries/{$itinerary->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('itineraries/show')
                ->has('itinerary.places', 2)
                ->where('itinerary.places.0', [
                    'id' => $louvre->id,
                    'name' => 'Louvre Museum',
                    'description' => 'Former royal palace, now the largest art museum in the world.',
                    'image_url' => $thumbnailUrl,
                    'themes' => [['id' => 'landmark', 'label' => 'Landmark'], ['id' => 'film', 'label' => 'Film & TV']],
                    'films' => ['The Dreamers', 'Funny Face', 'The Da Vinci Code'],
                ])
                ->where('itinerary.places.1', [
                    'id' => $bareStop->id,
                    'name' => 'Bare stop',
                    'description' => 'Short.',
                    'image_url' => null,
                    'themes' => [],
                    'films' => [],
                ]));
    }

    public function test_itinerary_page_lists_a_long_itinerary_in_stop_order()
    {
        $hamburg = City::factory()->create();
        $places = collect(range(1, 21))->map(fn (int $i) => Place::factory()->for($hamburg)->create(['name' => sprintf('Stop %02d', $i)]));
        // A fixed permutation that is neither alphabetical nor creation order.
        $stops = $places->sortBy(fn (Place $place, int $index) => ($index * 8) % 21)->values();
        $itinerary = Itinerary::factory()->for($hamburg)->create();
        $itinerary->syncStops($stops->pluck('id')->all());

        $this->get("/itineraries/{$itinerary->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('itineraries/show')
                ->has('itinerary.places', 21)
                ->where('itinerary.places', fn ($shown) => $shown->pluck('id')->all() === $stops->pluck('id')->all()));
    }

    public function test_itinerary_page_renders_when_the_itinerary_has_no_places()
    {
        $itinerary = Itinerary::factory()->create();

        $this->get("/itineraries/{$itinerary->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('itineraries/show')
                ->has('itinerary.places', 0));
    }

    public function test_query_counts_do_not_grow_with_the_number_of_records()
    {
        $place = Place::factory()->create();
        $itinerary = Itinerary::factory()->hasAttached($place)->create();
        $urls = ['/places', "/places/{$place->id}", "/itineraries/{$itinerary->id}"];

        $before = array_map($this->countQueries(...), $urls);

        Place::factory()->count(3)->hasAttached($itinerary)->create();
        Itinerary::factory()->count(3)->hasAttached($place)->create();

        $this->assertSame($before, array_map($this->countQueries(...), $urls));
    }

    public function test_regular_users_cannot_create_edit_or_delete_places()
    {
        $place = Place::factory()->create(['name' => 'Wat Pho']);
        $this->actingAs(User::factory()->create());

        $this->get('/places/create')->assertNotFound();
        $this->post('/places', ['name' => 'New place'])->assertMethodNotAllowed();
        $this->get("/places/{$place->id}/edit")->assertNotFound();
        $this->put("/places/{$place->id}", ['name' => 'Changed'])->assertMethodNotAllowed();
        $this->patch("/places/{$place->id}", ['name' => 'Changed'])->assertMethodNotAllowed();
        $this->delete("/places/{$place->id}")->assertMethodNotAllowed();

        $this->assertDatabaseCount('places', 1);
        $this->assertDatabaseHas('places', ['id' => $place->id, 'name' => 'Wat Pho']);
    }

    private function countQueries(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();

        return count(DB::getQueryLog());
    }
}
