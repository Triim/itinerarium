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
 * The Welcome page cards: the three newest published itineraries. Caching is covered by WelcomeCacheTest.
 */
class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_shows_only_the_three_most_recent_itineraries()
    {
        $oldest = Itinerary::factory()->create(['created_at' => now()->subDays(5)]);
        Itinerary::factory()->count(4)->create(['created_at' => now()->subDay()]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
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

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('itineraries', fn ($itineraries) => collect($itineraries)->pluck('id')->all() === [$newest->id, $sameTimeHigh->id, $sameTimeLow->id]));
    }

    public function test_welcome_cards_contain_exactly_the_title_slug_city_author_and_place_count()
    {
        $author = User::factory()->create(['name' => 'Ada Author']);
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $itinerary = Itinerary::factory()->for($author)->for($barcelona)
            ->hasAttached(Place::factory()->count(3)->for($barcelona))
            ->create(['title' => 'Barcelona on Foot']);

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

    public function test_welcome_page_does_not_expose_private_author_fields()
    {
        $author = User::factory()->create([
            'name' => 'Ada Author',
            'email' => 'ada.private@example.com',
            'is_admin' => true,
        ]);
        Itinerary::factory()->for($author)->create();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user', null)
                ->where('itineraries.0.user', ['id' => $author->id, 'name' => 'Ada Author']))
            ->assertDontSee('ada.private@example.com')
            ->assertDontSee('is_admin')
            ->assertDontSee('email_verified_at');

        // A signed-in visitor sees their own account in the shared props, but still nothing of the author's.
        $viewer = User::factory()->create();
        $this->actingAs($viewer)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.id', $viewer->id)
                ->where('itineraries.0.user', ['id' => $author->id, 'name' => 'Ada Author']))
            ->assertDontSee('ada.private@example.com');
    }
}
