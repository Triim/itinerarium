<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The public City pages.
 */
class CityTest extends TestCase
{
    use RefreshDatabase;

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
                ->where('cities.0', ['id' => $berlin->id, 'name' => 'Berlin', 'itineraries_count' => 0, 'places_count' => 3, 'cover' => null])
                ->where('cities.1', ['id' => $paris->id, 'name' => 'Paris', 'itineraries_count' => 1, 'places_count' => 1, 'cover' => null])
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
                ->where('city', ['id' => $berlin->id, 'name' => 'Berlin', 'itineraries_count' => 2, 'places_count' => 3, 'cover' => null])
                ->has('itineraries', 2)
                ->where('itineraries.0', [
                    'id' => $empty->id,
                    'title' => 'East Berlin',
                    'slug' => $empty->slug,
                    'places_count' => 0,
                    'user' => ['id' => $empty->user_id, 'name' => $empty->user->name],
                    'cover' => null,
                ])
                ->where('itineraries.1', [
                    'id' => $westBerlin->id,
                    'title' => 'West Berlin',
                    'slug' => $westBerlin->slug,
                    'places_count' => 3,
                    'user' => ['id' => $ada->id, 'name' => 'Ada'],
                    'cover' => null,
                ]));
    }

    public function test_city_page_renders_when_the_city_has_no_itineraries_or_places()
    {
        $city = City::factory()->create(['name' => 'Zurich']);

        $this->get("/cities/{$city->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cities/show', false)
                ->where('city', ['id' => $city->id, 'name' => 'Zurich', 'itineraries_count' => 0, 'places_count' => 0, 'cover' => null])
                ->has('itineraries', 0));
    }

    public function test_city_page_returns_not_found_for_a_missing_city()
    {
        $this->get('/cities/999')->assertNotFound();
    }
}
