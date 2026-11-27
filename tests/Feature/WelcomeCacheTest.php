<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Welcome list is cached with Cache::flexible() and forgotten whenever a card could change.
 */
class WelcomeCacheTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = Itinerary::WELCOME_CACHE_KEY;

    protected function setUp(): void
    {
        parent::setUp();

        // A fresh in-memory store per test, independent of the configured default.
        config(['cache.default' => 'array']);
        Cache::forgetDriver('array');
    }

    public function test_the_first_request_populates_the_cache()
    {
        $itinerary = Itinerary::factory()->create();

        $this->assertNull(Cache::get(self::KEY));

        $cards = $this->cards();

        $this->assertSame(['popular' => false, 'itineraries' => $cards], Cache::get(self::KEY));
        $this->assertSame([$itinerary->id], array_column($cards, 'id'));
    }

    public function test_a_repeated_request_is_served_from_the_cache()
    {
        $itinerary = Itinerary::factory()->create(['title' => 'Original title']);
        $this->cards();

        // A raw query fires no model events, so only the cache can explain the old title.
        DB::table('itineraries')->where('id', $itinerary->id)->update(['title' => 'Changed behind the cache']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $cards = $this->cards();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame('Original title', $cards[0]['title']);
        $this->assertSame([], $queries);
    }

    public function test_approving_an_itinerary_refreshes_the_cache()
    {
        $author = User::factory()->create();
        $city = City::factory()->create();
        $places = Place::factory()->count(2)->for($city)->create();

        $this->actingAs($author)
            ->post(route('userzone.itineraries.store'), [
                'title' => 'Brand new itinerary',
                'city_id' => $city->id,
                'place_ids' => $places->pluck('id')->all(),
            ]);
        $itinerary = Itinerary::sole();
        $this->actingAs($author)->post(route('userzone.itineraries.submit', $itinerary));
        // The empty list is now cached, and the pending itinerary is still not on it.
        $this->assertSame([], $this->cards());
        $this->assertSame(['popular' => false, 'itineraries' => []], Cache::get(self::KEY));

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('userzone.itineraries.approve', $itinerary))
            ->assertRedirect(route('userzone.itineraries.index'));

        // Well within the fresh window, so only invalidation can explain the new card.
        $cards = $this->cards();
        $this->assertCount(1, $cards);
        $this->assertSame('Brand new itinerary', $cards[0]['title']);
        $this->assertSame(2, $cards[0]['places_count']);
    }

    public function test_editing_an_itinerary_title_refreshes_the_cache()
    {
        $itinerary = Itinerary::factory()->create(['title' => 'Old title']);
        $this->assertSame('Old title', $this->cards()[0]['title']);

        $this->actingAs($itinerary->user)
            ->put(route('userzone.itineraries.update', $itinerary), [
                'title' => 'New title',
                'city_id' => $itinerary->city_id,
            ])
            ->assertRedirect();

        $this->assertSame('New title', $this->cards()[0]['title']);
    }

    public function test_deleting_an_itinerary_refreshes_the_cache()
    {
        $older = Itinerary::factory()->create(['created_at' => now()->subDays(3)]);
        $kept = Itinerary::factory()->count(3)->create(['created_at' => now()->subDay()]);
        $this->assertNotContains($older->id, array_column($this->cards(), 'id'));

        $deleted = $kept->first();
        $this->actingAs($deleted->user)
            ->delete(route('userzone.itineraries.destroy', $deleted))
            ->assertRedirect();

        $ids = array_column($this->cards(), 'id');
        $this->assertNotContains($deleted->id, $ids);
        $this->assertContains($older->id, $ids);
        $this->assertCount(3, $ids);
    }

    public function test_changing_selected_places_refreshes_the_place_count()
    {
        $city = City::factory()->create();
        $places = Place::factory()->count(3)->for($city)->create();
        $itinerary = Itinerary::factory()->for($city)->hasAttached($places->take(1))->create();
        $this->assertSame(1, $this->cards()[0]['places_count']);

        // Same title and city: only the pivot changes, so no Itinerary model event can help.
        $this->actingAs($itinerary->user)
            ->put(route('userzone.itineraries.update', $itinerary), [
                'title' => $itinerary->title,
                'city_id' => $city->id,
                'place_ids' => $places->pluck('id')->all(),
            ])
            ->assertRedirect();

        $this->assertSame(3, $this->cards()[0]['places_count']);

        $this->actingAs($itinerary->user)
            ->put(route('userzone.itineraries.update', $itinerary), [
                'title' => $itinerary->title,
                'city_id' => $city->id,
                'place_ids' => [],
            ])
            ->assertRedirect();

        $this->assertSame(0, $this->cards()[0]['places_count']);
    }

    public function test_a_welcome_read_between_saving_and_syncing_places_is_not_kept()
    {
        $author = User::factory()->create();
        $city = City::factory()->create();
        $places = Place::factory()->count(2)->for($city)->create();

        // Simulates a concurrent visitor rebuilding the cache after the saved event but before sync().
        // Booting the model first registers its own listener ahead of this one.
        new Itinerary;
        Itinerary::saved(fn () => Itinerary::popularForWelcome());

        $this->actingAs($author)
            ->post(route('userzone.itineraries.store'), [
                'title' => 'Created with places',
                'city_id' => $city->id,
                'place_ids' => $places->pluck('id')->all(),
            ])
            ->assertRedirect();

        // A draft stays off the list even when it is rebuilt during saving.
        $this->assertSame([], $this->cards());

        $itinerary = Itinerary::sole();
        $itinerary->submitForReview();
        $itinerary->approve();
        $this->assertSame(2, $this->cards()[0]['places_count']);

        $this->actingAs($author)
            ->put(route('userzone.itineraries.update', $itinerary), [
                'title' => $itinerary->title,
                'city_id' => $city->id,
                'place_ids' => [$places->first()->id],
            ])
            ->assertRedirect();

        $this->assertSame(1, $this->cards()[0]['places_count']);
    }

    public function test_renaming_a_city_refreshes_the_city_name()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $city = City::factory()->create(['name' => 'Old City']);
        Itinerary::factory()->for($city)->create();
        $this->assertSame('Old City', $this->cards()[0]['city']['name']);

        $this->actingAs($admin)
            ->put(route('userzone.cities.update', $city), ['name' => 'New City'])
            ->assertRedirect();

        $this->assertSame(['id' => $city->id, 'name' => 'New City'], $this->cards()[0]['city']);
    }

    public function test_renaming_an_author_refreshes_the_author_name()
    {
        $author = User::factory()->create(['name' => 'Old Name']);
        Itinerary::factory()->for($author)->create();
        $this->assertSame('Old Name', $this->cards()[0]['user']['name']);

        $this->actingAs($author)
            ->patch(route('profile.update'), ['name' => 'New Name', 'email' => $author->email])
            ->assertRedirect();

        $this->assertSame(['id' => $author->id, 'name' => 'New Name'], $this->cards()[0]['user']);
    }

    public function test_deleting_an_associated_place_refreshes_the_place_count()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $city = City::factory()->create();
        $places = Place::factory()->count(2)->for($city)->create();
        Itinerary::factory()->for($city)->hasAttached($places)->create();
        $this->assertSame(2, $this->cards()[0]['places_count']);

        $this->actingAs($admin)
            ->delete(route('userzone.places.destroy', $places->first()))
            ->assertRedirect();

        $this->assertSame(1, $this->cards()[0]['places_count']);
    }

    public function test_deleting_an_author_account_removes_their_itineraries()
    {
        $author = User::factory()->create();
        $itinerary = Itinerary::factory()->for($author)->create();
        $this->assertSame([$itinerary->id], array_column($this->cards(), 'id'));

        $this->actingAs($author)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect();

        $this->assertSame([], $this->cards());
    }

    public function test_changing_only_an_authors_email_keeps_the_cache()
    {
        $author = User::factory()->create(['name' => 'Same Name']);
        Itinerary::factory()->for($author)->create();
        $cards = $this->cards();

        $this->actingAs($author)
            ->patch(route('profile.update'), ['name' => 'Same Name', 'email' => 'changed@example.com'])
            ->assertRedirect();

        $this->assertSame('changed@example.com', $author->fresh()->email);
        $this->assertSame($cards, Cache::get(self::KEY)['itineraries']);
    }

    public function test_saving_an_itinerary_refreshes_the_ranking()
    {
        $older = Itinerary::factory()->create(['created_at' => now()->subDay()]);
        $newer = Itinerary::factory()->create();
        $this->assertSame([$newer->id, $older->id], array_column($this->cards(), 'id'));

        $this->actingAs(User::factory()->create())
            ->post(route('userzone.saved-itineraries.store', $older))
            ->assertRedirect();

        // Well within the fresh window, so only invalidation can explain the new order.
        $this->assertNull(Cache::get(self::KEY));
        $this->assertSame([$older->id, $newer->id], array_column($this->cards(), 'id'));
        $this->assertTrue(Cache::get(self::KEY)['popular']);
    }

    public function test_removing_a_save_refreshes_the_ranking()
    {
        $older = Itinerary::factory()->create(['created_at' => now()->subDay()]);
        $newer = Itinerary::factory()->create();
        $user = User::factory()->create();
        $user->savedItineraries()->attach($older);
        $this->assertSame([$older->id, $newer->id], array_column($this->cards(), 'id'));

        $this->actingAs($user)
            ->delete(route('userzone.saved-itineraries.destroy', $older))
            ->assertRedirect();

        $this->assertNull(Cache::get(self::KEY));
        $this->assertSame([$newer->id, $older->id], array_column($this->cards(), 'id'));
        $this->assertFalse(Cache::get(self::KEY)['popular']);
    }

    public function test_repeating_a_save_or_a_removal_keeps_the_cache()
    {
        $itinerary = Itinerary::factory()->create();
        $user = User::factory()->create();
        $user->savedItineraries()->attach($itinerary);
        $this->cards();
        $cached = Cache::get(self::KEY);

        // Already saved: the pivot row exists, so nothing is added and the cache stays.
        $this->actingAs($user)->post(route('userzone.saved-itineraries.store', $itinerary))->assertRedirect();
        $this->assertSame($cached, Cache::get(self::KEY));

        // The first removal deletes the row and clears the cache; the second has nothing to remove.
        $this->actingAs($user)->delete(route('userzone.saved-itineraries.destroy', $itinerary))->assertRedirect();
        $this->assertNull(Cache::get(self::KEY));
        $this->cards();
        $cached = Cache::get(self::KEY);
        $this->actingAs($user)->delete(route('userzone.saved-itineraries.destroy', $itinerary))->assertRedirect();
        $this->assertSame($cached, Cache::get(self::KEY));
        $this->assertDatabaseCount('itinerary_user', 0);
    }

    public function test_guests_and_authenticated_users_receive_the_same_itinerary_data()
    {
        Itinerary::factory()->count(3)->create();
        $viewer = User::factory()->create();

        $guestCards = $this->cards();

        $this->actingAs($viewer)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.id', $viewer->id)
                ->where('itineraries', $guestCards));

        // The cached payload is the public card list only, with nothing about the viewer.
        $this->assertSame(['popular' => false, 'itineraries' => $guestCards], Cache::get(self::KEY));
    }

    public function test_fresh_window_serves_the_cached_value_without_refreshing()
    {
        $itinerary = Itinerary::factory()->create(['title' => 'Cached']);
        $this->freezeTime();
        $this->cards();
        $this->renameBehindTheCache($itinerary, 'Changed');

        $this->travel(59)->seconds();

        $this->assertSame('Cached', $this->cards()[0]['title']);
        $this->assertSame('Cached', Cache::get(self::KEY)['itineraries'][0]['title']);
    }

    public function test_stale_window_serves_the_old_value_and_refreshes_after_the_response()
    {
        $itinerary = Itinerary::factory()->create(['title' => 'Cached']);
        $this->freezeTime();
        $this->cards();
        $this->renameBehindTheCache($itinerary, 'Changed');

        $this->travel(61)->seconds();

        // The visitor gets the stale list at once; the deferred refresh runs after the response.
        $this->assertSame('Cached', $this->cards()[0]['title']);
        $this->assertSame('Changed', Cache::get(self::KEY)['itineraries'][0]['title']);

        $this->assertSame('Changed', $this->cards()[0]['title']);
    }

    public function test_explicit_invalidation_is_not_overridden_by_the_stale_window()
    {
        $itinerary = Itinerary::factory()->create(['title' => 'Cached']);
        $this->freezeTime();
        $this->cards();

        $this->travel(120)->seconds();
        $itinerary->update(['title' => 'Edited']);

        $this->assertNull(Cache::get(self::KEY));
        $this->assertSame('Edited', $this->cards()[0]['title']);
    }

    private function cards(): array
    {
        $this->app['auth']->forgetGuards();

        $cards = null;
        $this->get('/')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$cards) {
                $page->component('welcome');
                $cards = $page->toArray()['props']['itineraries'];
            });

        return $cards;
    }

    private function renameBehindTheCache(Itinerary $itinerary, string $title): void
    {
        DB::table('itineraries')->where('id', $itinerary->id)->update(['title' => $title]);
    }
}
