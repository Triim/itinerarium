<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * draft → pending (the owner submits) → published (an administrator approves).
 */
class ItineraryPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_itinerary_created_in_the_application_starts_as_a_draft()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('userzone.itineraries.store'), ['title' => 'A weekend away', 'city_id' => City::factory()->create()->id])
            ->assertRedirect(route('userzone.itineraries.index'));

        $this->assertSame(Itinerary::DRAFT, Itinerary::sole()->status);
    }

    public function test_the_owner_can_submit_a_draft_for_review()
    {
        $itinerary = Itinerary::factory()->draft()->create();

        $this->actingAs($itinerary->user)
            ->post(route('userzone.itineraries.submit', $itinerary))
            ->assertRedirect(route('userzone.itineraries.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(Itinerary::PENDING, $itinerary->fresh()->status);
    }

    public function test_other_users_and_administrators_cannot_submit_someone_elses_itinerary()
    {
        $itinerary = Itinerary::factory()->draft()->create();

        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)
                ->post(route('userzone.itineraries.submit', $itinerary))
                ->assertForbidden();
        }

        $this->assertSame(Itinerary::DRAFT, $itinerary->fresh()->status);
    }

    public function test_an_administrator_can_approve_a_pending_itinerary()
    {
        $itinerary = Itinerary::factory()->pending()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('userzone.itineraries.approve', $itinerary))
            ->assertRedirect(route('userzone.itineraries.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(Itinerary::PUBLISHED, $itinerary->fresh()->status);
    }

    public function test_regular_users_cannot_approve_even_their_own_itinerary()
    {
        $itinerary = Itinerary::factory()->pending()->create();

        foreach ([$itinerary->user, User::factory()->create()] as $user) {
            $this->actingAs($user)
                ->post(route('userzone.itineraries.approve', $itinerary))
                ->assertForbidden();
        }

        $this->assertSame(Itinerary::PENDING, $itinerary->fresh()->status);
    }

    public function test_guests_are_sent_to_the_login_page()
    {
        $itinerary = Itinerary::factory()->pending()->create();

        $this->post(route('userzone.itineraries.submit', $itinerary))->assertRedirect('/login');
        $this->post(route('userzone.itineraries.approve', $itinerary))->assertRedirect('/login');

        $this->assertSame(Itinerary::PENDING, $itinerary->fresh()->status);
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_transitions_are_rejected_without_changes(string $action, string $status)
    {
        $itinerary = Itinerary::factory()->create(['status' => $status, 'updated_at' => now()->subDay()]);
        $before = $itinerary->fresh()->getAttributes();
        $user = $action === 'approve' ? User::factory()->admin()->create() : $itinerary->user;

        $this->actingAs($user)
            ->from(route('userzone.itineraries.index'))
            ->post(route("userzone.itineraries.{$action}", $itinerary))
            ->assertRedirect(route('userzone.itineraries.index'))
            ->assertSessionHasErrors('status');

        $this->assertEquals($before, $itinerary->fresh()->getAttributes());
    }

    public static function invalidTransitions(): array
    {
        return [
            'submit a pending itinerary again' => ['submit', Itinerary::PENDING],
            'submit a published itinerary' => ['submit', Itinerary::PUBLISHED],
            'approve a draft' => ['approve', Itinerary::DRAFT],
            'approve a published itinerary again' => ['approve', Itinerary::PUBLISHED],
        ];
    }
}
