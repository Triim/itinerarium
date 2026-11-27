<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_renders_an_empty_list_when_there_are_no_itineraries()
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->has('itineraries', 0));
    }

    public function test_welcome_page_shows_a_single_itinerary()
    {
        $itinerary = Itinerary::factory()->create();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('itineraries', 1)
                ->where('itineraries.0.id', $itinerary->id));
    }

    public function test_welcome_page_shows_two_itineraries()
    {
        Itinerary::factory()->count(2)->create();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('itineraries', 2));
    }

    public function test_welcome_page_shows_three_itineraries()
    {
        Itinerary::factory()->count(3)->create();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('itineraries', 3));
    }

    public function test_welcome_page_shows_only_the_three_most_recent_itineraries()
    {
        $oldest = Itinerary::factory()->create(['created_at' => now()->subDays(5)]);
        Itinerary::factory()->count(4)->create(['created_at' => now()->subDay()]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('itineraries', 3)
                ->where('itineraries', fn ($itineraries) => ! collect($itineraries)->contains('id', $oldest->id)));
    }

    public function test_welcome_page_orders_by_newest_first_with_id_as_tie_breaker()
    {
        // Created first but dated latest, so ordering must use created_at rather than insertion order.
        $newest = Itinerary::factory()->create(['created_at' => now()]);
        Itinerary::factory()->create(['created_at' => now()->subDays(2)]);
        $sameTimeLow = Itinerary::factory()->create(['created_at' => now()->subDay()]);
        $sameTimeHigh = Itinerary::factory()->create(['created_at' => $sameTimeLow->created_at]);

        $expected = [$newest->id, $sameTimeHigh->id, $sameTimeLow->id];

        foreach (range(1, 2) as $request) {
            $this->get('/')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('itineraries', fn ($itineraries) => collect($itineraries)->pluck('id')->all() === $expected));
        }
    }

    public function test_welcome_page_shows_title_city_author_and_place_count()
    {
        $author = User::factory()->create(['name' => 'Ada Author']);
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $places = Place::factory()->count(3)->for($barcelona)->create();
        $itinerary = Itinerary::factory()->for($author)->for($barcelona)->hasAttached($places)->create([
            'title' => 'Barcelona on Foot',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('itineraries.0', fn (Assert $card) => $card
                    ->where('id', $itinerary->id)
                    ->where('title', 'Barcelona on Foot')
                    ->where('slug', $itinerary->slug)
                    ->where('places_count', 3)
                    ->where('city', ['id' => $barcelona->id, 'name' => 'Barcelona'])
                    ->where('user', ['id' => $author->id, 'name' => 'Ada Author'])));
    }

    public function test_welcome_page_counts_places_for_each_itinerary_separately()
    {
        $city = City::factory()->create();
        $empty = Itinerary::factory()->for($city)->create(['created_at' => now()->subDay()]);
        $full = Itinerary::factory()->for($city)->hasAttached(Place::factory()->count(2)->for($city))->create();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('itineraries.0.id', $full->id)
                ->where('itineraries.0.places_count', 2)
                ->where('itineraries.1.id', $empty->id)
                ->where('itineraries.1.places_count', 0));
    }

    public function test_welcome_cards_link_to_existing_public_itinerary_pages()
    {
        Itinerary::factory()->count(3)->create();

        $cards = [];
        $this->get('/')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$cards) {
                $cards = collect($page->toArray()['props']['itineraries'])->pluck('slug', 'id')->all();
            });

        $this->assertCount(3, $cards);

        foreach ($cards as $id => $slug) {
            $this->get(route('itineraries.show', $slug))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('itineraries/show')
                    ->where('itinerary.id', $id));
        }
    }

    public function test_welcome_page_does_not_expose_private_author_fields()
    {
        $author = User::factory()->create([
            'name' => 'Ada Author',
            'email' => 'ada.private@example.com',
            'is_admin' => true,
        ]);
        Itinerary::factory()->for($author)->create();

        $response = $this->get('/')->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->where('itineraries.0.user', ['id' => $author->id, 'name' => 'Ada Author']));

        $response->assertDontSee('ada.private@example.com');
        $response->assertDontSee('is_admin');
        $response->assertDontSee('email_verified_at');
    }

    public function test_guests_can_view_the_welcome_page()
    {
        Itinerary::factory()->create();

        $this->assertGuest();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('auth.user', null)
                ->has('itineraries', 1));
    }

    public function test_authenticated_users_can_view_the_welcome_page()
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create(['email' => 'author.private@example.com']);
        Itinerary::factory()->for($author)->create();

        $this->actingAs($viewer)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('auth.user.id', $viewer->id)
                ->has('itineraries', 1)
                ->where('itineraries.0.user', ['id' => $author->id, 'name' => $author->name]))
            ->assertDontSee('author.private@example.com');
    }
}
