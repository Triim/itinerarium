<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The public Place and Itinerary pages, as guests see them.
 */
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

    public function test_places_list_shares_theme_ids_thumbnails_and_the_theme_vocabulary()
    {
        $berlin = City::factory()->create(['name' => 'Berlin']);
        // Stored out of vocabulary order, with a duplicate and an unknown id.
        $tempelhof = Place::factory()->for($berlin)->create([
            'name' => 'Tempelhof Field',
            'themes' => ['unusual', 'history', 'film', 'history', 'retired'],
            'films' => ['The Bourne Supremacy'],
        ]);
        $tempelhof->addMedia(UploadedFile::fake()->image('tempelhof.jpg', 800, 600))->toMediaCollection('images');
        $bare = Place::factory()->for($berlin)->create(['name' => 'Bare spot']);

        // Exact arrays: no coordinates, films, descriptions or media records in the directory.
        $this->get('/places')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('places/index')
                ->where('themes', [
                    ['id' => 'landmark', 'label' => 'Landmark'],
                    ['id' => 'film', 'label' => 'Film & TV'],
                    ['id' => 'architecture', 'label' => 'Architecture'],
                    ['id' => 'history', 'label' => 'History'],
                    ['id' => 'unusual', 'label' => 'Unusual places'],
                    ['id' => 'local_food', 'label' => 'Local food'],
                    ['id' => 'experience', 'label' => 'Experiences'],
                ])
                ->where('selected_city', null)
                ->where('cities.0.places.0', [
                    'id' => $bare->id,
                    'name' => 'Bare spot',
                    'itineraries_count' => 0,
                    'image_url' => null,
                    'themes' => [],
                ])
                ->where('cities.0.places.1', [
                    'id' => $tempelhof->id,
                    'name' => 'Tempelhof Field',
                    'itineraries_count' => 0,
                    'image_url' => $tempelhof->getFirstMedia('images')->getUrl('thumbnail'),
                    'themes' => ['film', 'history', 'unusual'],
                ]));
    }

    public function test_places_list_can_preselect_a_city_from_the_query_string()
    {
        $berlin = City::factory()->create(['name' => 'Berlin']);
        $paris = City::factory()->create(['name' => 'Paris']);
        Place::factory()->for($berlin)->create();
        Place::factory()->for($paris)->create();

        $this->get("/places?city={$paris->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('places/index')
                ->where('selected_city', $paris->id)
                // Every city stays available, so the visitor can switch without reloading.
                ->has('cities', 2));
    }

    public function test_invalid_city_query_values_fall_back_to_all_cities()
    {
        $berlin = City::factory()->create();
        Place::factory()->for($berlin)->create();
        $empty = City::factory()->create();

        foreach (["{$berlin->id}abc", 'berlin', '', '999', (string) $empty->id, '-1', '1.5'] as $value) {
            $this->get('/places?city='.urlencode($value))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('selected_city', null)->has('cities', 1));
        }

        $this->get("/places?city[]={$berlin->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('selected_city', null));
    }

    public function test_place_page_shows_its_themes_films_and_image()
    {
        $paris = City::factory()->create(['name' => 'Paris']);
        $louvre = Place::factory()->for($paris)->create([
            'name' => 'Louvre Museum',
            'themes' => ['film', 'landmark', 'architecture'],
            'films' => ['The Dreamers', 'Funny Face', 'The Da Vinci Code'],
        ]);
        $louvre->addMedia(UploadedFile::fake()->image('louvre.jpg', 800, 600))->toMediaCollection('images');
        $bare = Place::factory()->for($paris)->create();

        $this->get("/places/{$louvre->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('places/show')
                ->where('place.image_url', $louvre->getFirstMedia('images')->getUrl('details'))
                ->where('place.themes', [
                    ['id' => 'landmark', 'label' => 'Landmark'],
                    ['id' => 'film', 'label' => 'Film & TV'],
                    ['id' => 'architecture', 'label' => 'Architecture'],
                ])
                ->where('place.films', ['The Dreamers', 'Funny Face', 'The Da Vinci Code'])
                ->missing('place.latitude')
                ->missing('place.media'));

        $this->get("/places/{$bare->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('place.image_url', null)
                ->where('place.themes', [])
                ->where('place.films', [])
                ->has('itineraries', 0));
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
                ->where('city', ['id' => $barcelona->id, 'name' => 'Barcelona'])
                ->has('itineraries', 2)
                ->where('itineraries.0.id', $weekend->id)
                ->where('itineraries.0.title', 'A Weekend in Barcelona')
                ->where('itineraries.0.user.name', $weekend->user->name)
                ->where('itineraries.1.id', $onFoot->id));
    }

    public function test_place_page_returns_not_found_for_a_missing_place()
    {
        $this->get('/places/999')->assertNotFound();
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
                ->where('itineraries.1.id', $onFoot->id)
                ->where('itineraries.1.places_count', 1)
                ->where('itineraries.1.city.name', 'Hamburg')
                // The exact card: no author email or other private fields.
                ->where('itineraries.2', [
                    'id' => $westBerlin->id,
                    'title' => 'West Berlin: cinema, Bauhaus and modernism',
                    'slug' => $westBerlin->slug,
                    'places_count' => 3,
                    'city' => ['id' => $berlin->id, 'name' => 'Berlin'],
                    'user' => ['id' => $ada->id, 'name' => 'Ada'],
                    'cover' => null,
                ]));
    }

    public function test_itinerary_page_shows_only_its_own_places_in_stop_order()
    {
        $barcelona = City::factory()->create();
        $sagradaFamilia = Place::factory()->for($barcelona)->create(['name' => 'Sagrada Família']);
        $casaBatllo = Place::factory()->for($barcelona)->create(['name' => 'Casa Batlló']);
        $parkGuell = Place::factory()->for($barcelona)->create(['name' => 'Park Güell']);
        Place::factory()->for($barcelona)->create(['name' => 'Barcelona Cathedral']);
        $itinerary = Itinerary::factory()->for($barcelona)->create();
        // Neither alphabetical nor creation order.
        $itinerary->syncStops([$sagradaFamilia->id, $parkGuell->id, $casaBatllo->id]);

        $this->get(route('itineraries.show', $itinerary))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('itineraries/show')
                ->where('itinerary.title', $itinerary->title)
                ->where('itinerary.city', ['id' => $barcelona->id, 'name' => $barcelona->name])
                ->where('itinerary.user', ['id' => $itinerary->user_id, 'name' => $itinerary->user->name])
                ->has('itinerary.places', 3)
                ->where('itinerary.places.0.name', 'Sagrada Família')
                ->where('itinerary.places.1.name', 'Park Güell')
                ->where('itinerary.places.2.name', 'Casa Batlló'));
    }

    public function test_itinerary_page_shares_place_descriptions_themes_films_and_thumbnails()
    {
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

        $this->get(route('itineraries.show', $itinerary))
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

    public function test_there_are_no_public_routes_to_change_places()
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
}
