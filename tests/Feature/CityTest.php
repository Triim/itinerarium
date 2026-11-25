<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
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
