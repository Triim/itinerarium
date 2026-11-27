<?php

namespace Tests\Feature;

use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Saving other authors' published itineraries, removing saves and the Saved itineraries page.
 */
class UserzoneSavedItineraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_save_a_published_itinerary()
    {
        $user = User::factory()->create();
        $itinerary = Itinerary::factory()->create();

        $this->actingAs($user)
            ->from(route('itineraries.show', $itinerary))
            ->post(route('userzone.saved-itineraries.store', $itinerary))
            ->assertRedirect(route('itineraries.show', $itinerary));

        $this->assertDatabaseHas('itinerary_user', ['itinerary_id' => $itinerary->id, 'user_id' => $user->id]);
    }

    public function test_saving_twice_keeps_a_single_save()
    {
        $user = User::factory()->create();
        $itinerary = Itinerary::factory()->create();

        $this->actingAs($user)->post(route('userzone.saved-itineraries.store', $itinerary))->assertRedirect();
        $this->actingAs($user)->post(route('userzone.saved-itineraries.store', $itinerary))->assertRedirect();

        $this->assertDatabaseCount('itinerary_user', 1);
    }

    public function test_an_author_cannot_save_their_own_itinerary()
    {
        $itinerary = Itinerary::factory()->create();

        $this->actingAs($itinerary->user)
            ->post(route('userzone.saved-itineraries.store', $itinerary))
            ->assertForbidden();

        $this->assertDatabaseCount('itinerary_user', 0);
    }

    public function test_draft_and_pending_itineraries_cannot_be_saved()
    {
        $user = User::factory()->create();

        foreach ([Itinerary::factory()->draft()->create(), Itinerary::factory()->pending()->create()] as $itinerary) {
            $this->actingAs($user)
                ->post(route('userzone.saved-itineraries.store', $itinerary))
                ->assertNotFound();
        }

        $this->assertDatabaseCount('itinerary_user', 0);
    }

    public function test_guests_are_sent_to_log_in_and_cannot_save_or_see_saves()
    {
        $itinerary = Itinerary::factory()->create();
        $itinerary->savedByUsers()->attach(User::factory()->create());

        $this->post(route('userzone.saved-itineraries.store', $itinerary))->assertRedirect(route('login'));
        $this->delete(route('userzone.saved-itineraries.destroy', $itinerary))->assertRedirect(route('login'));
        $this->get(route('userzone.saved-itineraries.index'))->assertRedirect(route('login'));

        $this->assertDatabaseCount('itinerary_user', 1);
    }

    public function test_a_user_can_remove_their_save_and_removing_again_is_harmless()
    {
        $user = User::factory()->create();
        $itinerary = Itinerary::factory()->create();
        $user->savedItineraries()->attach($itinerary);

        $this->actingAs($user)
            ->from(route('userzone.saved-itineraries.index'))
            ->delete(route('userzone.saved-itineraries.destroy', $itinerary))
            ->assertRedirect(route('userzone.saved-itineraries.index'));
        $this->actingAs($user)->delete(route('userzone.saved-itineraries.destroy', $itinerary))->assertRedirect();

        $this->assertDatabaseCount('itinerary_user', 0);
    }

    public function test_a_user_cannot_remove_another_users_save()
    {
        [$saver, $other] = User::factory()->count(2)->create();
        $itinerary = Itinerary::factory()->create();
        $saver->savedItineraries()->attach($itinerary);

        $this->actingAs($other)->delete(route('userzone.saved-itineraries.destroy', $itinerary))->assertRedirect();

        $this->assertEquals([$itinerary->id], $saver->savedItineraries()->pluck('itineraries.id')->all());
    }

    public function test_removing_a_save_keeps_the_itinerary_and_other_users_saves()
    {
        [$user, $other] = User::factory()->count(2)->create();
        $itinerary = Itinerary::factory()->hasAttached(Place::factory())->create();
        $itinerary->savedByUsers()->attach([$user->id, $other->id]);

        $this->actingAs($user)->delete(route('userzone.saved-itineraries.destroy', $itinerary))->assertRedirect();

        $this->assertModelExists($itinerary);
        $this->assertSame(Itinerary::PUBLISHED, $itinerary->fresh()->status);
        $this->assertCount(1, $itinerary->places);
        $this->assertEquals([$other->id], $itinerary->savedByUsers()->pluck('users.id')->all());
    }

    public function test_the_page_lists_only_the_current_users_saved_published_itineraries()
    {
        [$user, $other] = User::factory()->count(2)->create();
        $older = Itinerary::factory()->hasAttached(Place::factory()->count(2))->create(['title' => 'Older save']);
        $newer = Itinerary::factory()->create(['title' => 'Newer save']);
        $unpublished = Itinerary::factory()->create();
        $othersSave = Itinerary::factory()->create();

        $this->travel(-1)->days();
        $user->savedItineraries()->attach($older);
        $this->travelBack();
        $user->savedItineraries()->attach([$newer->id, $unpublished->id]);
        $other->savedItineraries()->attach($othersSave);
        // Saved while published, then moved back to a draft: kept in the table, hidden from the page.
        $unpublished->update(['status' => Itinerary::DRAFT]);

        $this->actingAs($user)
            ->get(route('userzone.saved-itineraries.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/saved-itineraries/index')
                ->has('itineraries', 2)
                ->where('itineraries.0.id', $newer->id)
                ->has('itineraries.1', fn (Assert $card) => $card
                    ->where('id', $older->id)
                    ->where('title', 'Older save')
                    ->where('slug', $older->slug)
                    ->where('places_count', 2)
                    ->where('city', $older->city->only(['id', 'name']))
                    ->where('user', $older->user->only(['id', 'name']))
                    ->has('cover')));

        $this->assertDatabaseHas('itinerary_user', ['itinerary_id' => $unpublished->id, 'user_id' => $user->id]);
    }

    public function test_the_page_is_empty_without_saves()
    {
        $user = User::factory()->create();
        // Authored itineraries are not saves.
        Itinerary::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('userzone.saved-itineraries.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/saved-itineraries/index')
                ->has('itineraries', 0));
    }

    public function test_the_public_page_tells_whether_the_viewer_saved_the_itinerary()
    {
        $user = User::factory()->create();
        $itinerary = Itinerary::factory()->create();
        $show = route('itineraries.show', $itinerary);

        $this->get($show)->assertInertia(fn (Assert $page) => $page->where('saved', false));
        $this->actingAs($user)->get($show)->assertInertia(fn (Assert $page) => $page->where('saved', false));

        $user->savedItineraries()->attach($itinerary);
        $this->actingAs($user)->get($show)->assertInertia(fn (Assert $page) => $page->where('saved', true));
        $this->actingAs(User::factory()->create())->get($show)->assertInertia(fn (Assert $page) => $page->where('saved', false));
    }

    public function test_saving_leaves_authorship_and_publication_unchanged()
    {
        $user = User::factory()->create();
        $itinerary = Itinerary::factory()->create();
        $author = $itinerary->user;

        $this->actingAs($user)->post(route('userzone.saved-itineraries.store', $itinerary))->assertRedirect();

        $this->assertTrue($itinerary->fresh()->user->is($author));
        $this->assertSame(Itinerary::PUBLISHED, $itinerary->fresh()->status);
        $this->assertEmpty($user->itineraries);
        // A saved itinerary is not listed among the user's own, and the author still manages it.
        $this->actingAs($user)->get(route('userzone.itineraries.index'))
            ->assertInertia(fn (Assert $page) => $page->has('itineraries', 0));
        $this->actingAs($user)->get(route('userzone.itineraries.edit', $itinerary))->assertForbidden();
        $this->actingAs($author)->get(route('userzone.itineraries.index'))
            ->assertInertia(fn (Assert $page) => $page->has('itineraries', 1)->where('itineraries.0.id', $itinerary->id));
    }

    public function test_the_page_query_count_does_not_grow_with_saves()
    {
        $user = User::factory()->create();
        $user->savedItineraries()->attach(Itinerary::factory()->hasAttached(Place::factory())->create());
        $before = $this->countPageQueries($user);

        $user->savedItineraries()->attach(Itinerary::factory()->count(5)->hasAttached(Place::factory()->count(2))->create());

        $this->assertSame($before, $this->countPageQueries($user));
    }

    private function countPageQueries(User $user): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('userzone.saved-itineraries.index'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
