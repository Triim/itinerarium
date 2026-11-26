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
 * Public pages show only published itineraries; drafts and pending ones are managed in the Userzone.
 */
class PublishedItineraryVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpublished_itineraries_have_no_public_page_even_for_their_owner_or_an_administrator()
    {
        $draft = Itinerary::factory()->draft()->create();
        $pending = Itinerary::factory()->pending()->create();
        $published = Itinerary::factory()->create();

        foreach ([null, $draft->user, $pending->user, User::factory()->admin()->create()] as $viewer) {
            $viewer ? $this->actingAs($viewer) : $this->app['auth']->forgetGuards();

            $this->get(route('itineraries.show', $draft))->assertNotFound();
            $this->get(route('itineraries.show', $pending))->assertNotFound();
            $this->get(route('itineraries.show', $published))->assertOk();
        }
    }

    public function test_public_lists_and_counts_include_only_published_itineraries()
    {
        $city = City::factory()->create(['name' => 'Barcelona']);
        $place = Place::factory()->for($city)->create();
        $published = Itinerary::factory()->for($city)->hasAttached($place)->create(['title' => 'Published route']);
        Itinerary::factory()->draft()->for($city)->hasAttached($place)->create(['title' => 'Draft route']);
        Itinerary::factory()->pending()->for($city)->hasAttached($place)->create(['title' => 'Pending route']);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->has('itineraries', 1)
            ->where('itineraries.0.id', $published->id));
        $this->get(route('itineraries.index'))->assertInertia(fn (Assert $page) => $page
            ->has('itineraries', 1)
            ->where('itineraries.0.id', $published->id));
        $this->get(route('cities.index'))->assertInertia(fn (Assert $page) => $page
            ->where('cities.0.itineraries_count', 1));
        $this->get(route('cities.show', $city))->assertInertia(fn (Assert $page) => $page
            ->where('city.itineraries_count', 1)
            ->has('itineraries', 1)
            ->where('itineraries.0.id', $published->id));
        $this->get(route('places.index'))->assertInertia(fn (Assert $page) => $page
            ->where('cities.0.places.0.itineraries_count', 1));
        $this->get(route('places.show', $place))->assertInertia(fn (Assert $page) => $page
            ->has('itineraries', 1)
            ->where('itineraries.0.id', $published->id));
    }

    public function test_an_approved_itinerary_becomes_visible_to_guests_everywhere()
    {
        $city = City::factory()->create();
        $place = Place::factory()->for($city)->create();
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('userzone.itineraries.store'), [
            'title' => 'Fresh route',
            'city_id' => $city->id,
            'place_ids' => [$place->id],
        ]);
        $itinerary = Itinerary::sole();
        $this->assertHiddenFromGuests($itinerary, $city, $place);

        $this->actingAs($owner)->post(route('userzone.itineraries.submit', $itinerary));
        $this->assertHiddenFromGuests($itinerary, $city, $place);

        $this->actingAs(User::factory()->admin()->create())->post(route('userzone.itineraries.approve', $itinerary));
        $this->app['auth']->forgetGuards();

        $this->get(route('itineraries.show', $itinerary))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('itinerary.title', 'Fresh route'));
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('itineraries.0.id', $itinerary->id));
        $this->get(route('itineraries.index'))->assertInertia(fn (Assert $page) => $page->where('itineraries.0.id', $itinerary->id));
        $this->get(route('cities.show', $city))->assertInertia(fn (Assert $page) => $page
            ->where('city.itineraries_count', 1)
            ->where('itineraries.0.id', $itinerary->id));
        $this->get(route('places.index'))->assertInertia(fn (Assert $page) => $page->where('cities.0.places.0.itineraries_count', 1));
        $this->get(route('places.show', $place))->assertInertia(fn (Assert $page) => $page->where('itineraries.0.id', $itinerary->id));
    }

    public function test_editing_a_pending_itinerary_returns_it_to_draft()
    {
        $itinerary = Itinerary::factory()->pending()->create();

        $this->actingAs($itinerary->user)
            ->put(route('userzone.itineraries.update', $itinerary), ['title' => 'Revised title', 'city_id' => $itinerary->city_id])
            ->assertRedirect(route('userzone.itineraries.index'));

        $this->assertSame(Itinerary::DRAFT, $itinerary->fresh()->status);
        $this->assertSame('Revised title', $itinerary->fresh()->title);
    }

    public function test_editing_keeps_drafts_as_drafts_and_published_itineraries_published()
    {
        $draft = Itinerary::factory()->draft()->create();
        $published = Itinerary::factory()->create();

        foreach ([$draft, $published] as $itinerary) {
            $this->actingAs($itinerary->user)
                ->put(route('userzone.itineraries.update', $itinerary), ['title' => 'Revised title', 'city_id' => $itinerary->city_id])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(Itinerary::DRAFT, $draft->fresh()->status);
        $this->assertSame(Itinerary::PUBLISHED, $published->fresh()->status);
    }

    public function test_the_status_cannot_be_set_through_the_edit_form()
    {
        $itinerary = Itinerary::factory()->draft()->create();

        $this->actingAs($itinerary->user)->put(route('userzone.itineraries.update', $itinerary), [
            'title' => 'Revised title',
            'city_id' => $itinerary->city_id,
            'status' => Itinerary::PUBLISHED,
        ]);
        $this->actingAs($itinerary->user)->post(route('userzone.itineraries.store'), [
            'title' => 'Another route',
            'city_id' => $itinerary->city_id,
            'status' => Itinerary::PUBLISHED,
        ]);

        $this->assertSame(0, Itinerary::published()->count());
    }

    public function test_owners_and_administrators_see_the_status_in_the_management_list()
    {
        $owner = User::factory()->create();
        $draft = Itinerary::factory()->draft()->for($owner)->create(['title' => 'A']);
        $pending = Itinerary::factory()->pending()->create(['title' => 'B']);

        $this->actingAs($owner)->get(route('userzone.itineraries.index'))->assertInertia(fn (Assert $page) => $page
            ->has('itineraries', 1)
            ->where('itineraries.0.status', Itinerary::DRAFT));

        $this->actingAs(User::factory()->admin()->create())->get(route('userzone.itineraries.index'))->assertInertia(fn (Assert $page) => $page
            ->has('itineraries', 2)
            ->where('itineraries.0.id', $draft->id)
            ->where('itineraries.1.id', $pending->id)
            ->where('itineraries.1.status', Itinerary::PENDING));
    }

    public function test_the_private_show_action_sends_unpublished_itineraries_to_the_edit_page()
    {
        $itinerary = Itinerary::factory()->pending()->create();

        $this->actingAs($itinerary->user)
            ->get(route('userzone.itineraries.show', $itinerary))
            ->assertRedirect(route('userzone.itineraries.edit', $itinerary));
        $this->actingAs(User::factory()->create())
            ->get(route('userzone.itineraries.show', $itinerary))
            ->assertForbidden();
    }

    private function assertHiddenFromGuests(Itinerary $itinerary, City $city, Place $place): void
    {
        $this->app['auth']->forgetGuards();

        $this->get(route('itineraries.show', $itinerary))->assertNotFound();
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('itineraries', 0));
        $this->get(route('itineraries.index'))->assertInertia(fn (Assert $page) => $page->has('itineraries', 0));
        $this->get(route('cities.show', $city))->assertInertia(fn (Assert $page) => $page
            ->where('city.itineraries_count', 0)
            ->has('itineraries', 0));
        $this->get(route('places.index'))->assertInertia(fn (Assert $page) => $page->where('cities.0.places.0.itineraries_count', 0));
        $this->get(route('places.show', $place))->assertInertia(fn (Assert $page) => $page->has('itineraries', 0));
    }
}
