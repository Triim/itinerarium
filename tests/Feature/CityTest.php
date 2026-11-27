<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_see_all_cities_with_their_itinerary_counts()
    {
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        City::factory()->create(['name' => 'Bangkok']);
        Itinerary::factory()->count(2)->for($barcelona)->create();

        $this->get('/cities')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cities/index', false)
                ->has('cities', 2)
                ->where('cities.0.name', 'Bangkok')
                ->where('cities.0.itineraries_count', 0)
                ->where('cities.1.name', 'Barcelona')
                ->where('cities.1.itineraries_count', 2));
    }

    public function test_cities_list_counts_their_own_itineraries_and_places()
    {
        $berlin = City::factory()->create(['name' => 'Berlin']);
        $paris = City::factory()->create(['name' => 'Paris']);
        City::factory()->create(['name' => 'Zurich']);
        Place::factory()->count(3)->for($berlin)->create();
        Place::factory()->for($paris)->create();
        Itinerary::factory()->for($paris)->create();

        $this->get('/cities')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cities/index', false)
                ->has('cities', 3)
                ->where('cities.0', ['id' => $berlin->id, 'name' => 'Berlin', 'itineraries_count' => 0, 'places_count' => 3])
                ->where('cities.1', ['id' => $paris->id, 'name' => 'Paris', 'itineraries_count' => 1, 'places_count' => 1])
                ->where('cities.2.name', 'Zurich')
                ->where('cities.2.itineraries_count', 0)
                ->where('cities.2.places_count', 0));
    }

    public function test_city_page_shows_its_counts_and_itineraries_with_their_place_counts()
    {
        $berlin = City::factory()->create(['name' => 'Berlin']);
        $paris = City::factory()->create(['name' => 'Paris']);
        $places = Place::factory()->count(3)->for($berlin)->create();
        Place::factory()->count(2)->for($paris)->create();
        $ada = User::factory()->create(['name' => 'Ada']);
        $westBerlin = Itinerary::factory()->for($berlin)->for($ada)->create(['title' => 'West Berlin']);
        $westBerlin->syncStops($places->pluck('id')->all());
        $empty = Itinerary::factory()->for($berlin)->create(['title' => 'East Berlin']);
        Itinerary::factory()->for($paris)->create();

        $this->get("/cities/{$berlin->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cities/show', false)
                ->where('city', ['id' => $berlin->id, 'name' => 'Berlin', 'itineraries_count' => 2, 'places_count' => 3])
                ->has('itineraries', 2)
                ->where('itineraries.0', [
                    'id' => $empty->id,
                    'title' => 'East Berlin',
                    'slug' => $empty->slug,
                    'places_count' => 0,
                    'user' => ['id' => $empty->user_id, 'name' => $empty->user->name],
                ])
                ->where('itineraries.1', [
                    'id' => $westBerlin->id,
                    'title' => 'West Berlin',
                    'slug' => $westBerlin->slug,
                    'places_count' => 3,
                    'user' => ['id' => $ada->id, 'name' => 'Ada'],
                ]));
    }

    public function test_city_page_renders_when_the_city_has_no_itineraries_or_places()
    {
        $city = City::factory()->create(['name' => 'Zurich']);

        $this->get("/cities/{$city->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cities/show', false)
                ->where('city', ['id' => $city->id, 'name' => 'Zurich', 'itineraries_count' => 0, 'places_count' => 0])
                ->has('itineraries', 0));
    }

    public function test_cities_page_renders_when_there_are_no_cities()
    {
        $this->get('/cities')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cities/index', false)
                ->has('cities', 0));
    }

    public function test_guests_can_see_only_the_itineraries_of_the_selected_city()
    {
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $bangkok = City::factory()->create(['name' => 'Bangkok']);
        $itinerary = Itinerary::factory()->for($barcelona)->create(['title' => 'Barcelona on Foot']);
        Itinerary::factory()->for($bangkok)->create(['title' => 'First Day in Bangkok']);

        $this->get("/cities/{$barcelona->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cities/show', false)
                ->where('city.name', 'Barcelona')
                ->has('itineraries', 1)
                ->where('itineraries.0.id', $itinerary->id)
                ->where('itineraries.0.title', 'Barcelona on Foot')
                ->where('itineraries.0.user.name', $itinerary->user->name));
    }

    public function test_city_page_renders_when_the_city_has_no_itineraries()
    {
        $city = City::factory()->create();

        $this->get("/cities/{$city->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cities/show', false)
                ->has('itineraries', 0));
    }

    public function test_city_page_returns_not_found_for_a_missing_city()
    {
        $this->get('/cities/999')->assertNotFound();
    }
}
