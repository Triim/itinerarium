<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\User;
use App\Notifications\ItineraryPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Public itinerary pages live at readable slug URLs (/itineraries/a-weekend-in-madrid-17).
 * Old numeric links redirect there, and the Userzone keeps its numeric management URLs.
 */
class PublicItineraryUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_itinerary_gets_a_slug_from_its_title_and_id()
    {
        $itinerary = Itinerary::factory()->create(['title' => 'A Weekend in Madrid!']);

        $this->assertSame("a-weekend-in-madrid-{$itinerary->id}", $itinerary->slug);
        $this->assertSame($itinerary->slug, $itinerary->fresh()->slug);
    }

    public function test_itineraries_with_the_same_title_get_unique_slugs()
    {
        $first = Itinerary::factory()->create(['title' => 'Old Town']);
        $second = Itinerary::factory()->create(['title' => 'Old Town']);

        $this->assertSame("old-town-{$first->id}", $first->slug);
        $this->assertSame("old-town-{$second->id}", $second->slug);
    }

    public function test_a_title_with_nothing_to_slug_still_gets_a_slug()
    {
        $itinerary = Itinerary::factory()->create(['title' => '???']);

        $this->assertSame("itinerary-{$itinerary->id}", $itinerary->slug);
    }

    public function test_a_published_itinerary_is_public_at_its_slug_url()
    {
        $itinerary = Itinerary::factory()->create(['title' => 'A Weekend in Madrid']);

        $this->assertSame(url("/itineraries/a-weekend-in-madrid-{$itinerary->id}"), route('itineraries.show', $itinerary));

        $this->get("/itineraries/a-weekend-in-madrid-{$itinerary->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('itineraries/show')
                ->where('itinerary.id', $itinerary->id));
    }

    public function test_an_old_numeric_url_redirects_permanently_to_the_slug_url()
    {
        $itinerary = Itinerary::factory()->create();

        $this->get("/itineraries/{$itinerary->id}")
            ->assertStatus(301)
            ->assertRedirect(route('itineraries.show', $itinerary));
    }

    public function test_invalid_slugs_return_not_found()
    {
        $itinerary = Itinerary::factory()->create(['title' => 'Old Town']);

        $this->get('/itineraries/no-such-itinerary')->assertNotFound();
        $this->get("/itineraries/wrong-title-{$itinerary->id}")->assertNotFound();
        $this->get('/itineraries/'.($itinerary->id + 1))->assertNotFound();
    }

    public function test_draft_and_pending_itineraries_stay_private_at_slug_and_numeric_urls()
    {
        foreach ([Itinerary::factory()->draft()->create(), Itinerary::factory()->pending()->create()] as $itinerary) {
            $this->get("/itineraries/{$itinerary->slug}")->assertNotFound();
            // No redirect that would reveal the slug of an unpublished itinerary.
            $this->get("/itineraries/{$itinerary->id}")->assertNotFound();
            $this->actingAs($itinerary->user)->get("/itineraries/{$itinerary->id}")->assertNotFound();
        }
    }

    public function test_userzone_crud_routes_keep_numeric_ids()
    {
        $owner = User::factory()->create();
        $itinerary = Itinerary::factory()->for($owner)->draft()->create(['title' => 'Old Town']);

        $this->assertSame(url("/user/itineraries/{$itinerary->id}/edit"), route('userzone.itineraries.edit', $itinerary));

        $this->actingAs($owner)->get("/user/itineraries/{$itinerary->id}/edit")->assertOk();
        $this->actingAs($owner)
            ->put("/user/itineraries/{$itinerary->id}", ['title' => 'New Town', 'city_id' => $itinerary->city_id])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->actingAs($owner)->get("/user/itineraries/{$itinerary->slug}/edit")->assertNotFound();
        $this->actingAs($owner)->delete("/user/itineraries/{$itinerary->id}")->assertRedirect();

        $this->assertModelMissing($itinerary);
    }

    public function test_editing_the_title_keeps_the_existing_slug_and_link()
    {
        $owner = User::factory()->create();
        $itinerary = Itinerary::factory()->for($owner)->create(['title' => 'Old Town']);
        $link = route('itineraries.show', $itinerary);

        $this->actingAs($owner)
            ->put(route('userzone.itineraries.update', $itinerary), ['title' => 'New Town', 'city_id' => City::factory()->create()->id])
            ->assertSessionHasNoErrors();

        $this->assertSame('New Town', $itinerary->fresh()->title);
        $this->assertSame("old-town-{$itinerary->id}", $itinerary->fresh()->slug);
        $this->get($link)->assertOk()->assertInertia(fn (Assert $page) => $page->where('itinerary.title', 'New Town'));
    }

    public function test_the_publication_email_links_to_the_canonical_slug_url()
    {
        Notification::fake();
        $itinerary = Itinerary::factory()->pending()->create(['title' => 'Three days in Lisbon']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('userzone.itineraries.approve', $itinerary));

        Notification::assertSentTo($itinerary->user, fn (ItineraryPublished $notification) => $notification->toMail($itinerary->user)->actionUrl
            === url("/itineraries/three-days-in-lisbon-{$itinerary->id}"));
        $this->get(url("/itineraries/three-days-in-lisbon-{$itinerary->id}"))->assertOk();
    }
}
