<?php

namespace Tests\Feature;

use App\Models\Itinerary;
use App\Models\User;
use App\Notifications\ItineraryPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The author is emailed once, when an administrator approves a pending itinerary.
 */
class ItineraryPublishedNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_approving_a_pending_itinerary_emails_its_author()
    {
        $itinerary = Itinerary::factory()->pending()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('userzone.itineraries.approve', $itinerary))
            ->assertSessionHasNoErrors();

        Notification::assertSentToTimes($itinerary->user, ItineraryPublished::class, 1);
        Notification::assertSentTo($itinerary->user, ItineraryPublished::class, fn ($notification, array $channels) => $channels === ['mail']
            && $notification->itinerary->is($itinerary));
        Notification::assertNotSentTo($admin, ItineraryPublished::class);
    }

    public function test_the_email_names_the_author_itinerary_and_city_and_links_to_the_public_page()
    {
        $itinerary = Itinerary::factory()->pending()->create(['title' => 'Three days in Lisbon']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('userzone.itineraries.approve', $itinerary));

        Notification::assertSentTo($itinerary->user, function (ItineraryPublished $notification) use ($itinerary) {
            $mail = $notification->toMail($itinerary->user);
            $text = implode(' ', [$mail->subject, $mail->greeting, ...$mail->introLines]);

            return str_contains($text, $itinerary->user->name)
                && str_contains($text, 'Three days in Lisbon')
                && str_contains($text, $itinerary->city->name)
                && str_contains($text, 'now public')
                && $mail->actionUrl === route('itineraries.show', $itinerary);
        });
    }

    public function test_a_repeated_approval_sends_no_second_email()
    {
        $itinerary = Itinerary::factory()->pending()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('userzone.itineraries.approve', $itinerary))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('userzone.itineraries.approve', $itinerary))->assertSessionHasErrors('status');

        Notification::assertSentToTimes($itinerary->user, ItineraryPublished::class, 1);
    }

    public function test_approving_a_draft_sends_nothing()
    {
        $itinerary = Itinerary::factory()->draft()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('userzone.itineraries.approve', $itinerary))
            ->assertSessionHasErrors('status');

        Notification::assertNothingSent();
    }

    public function test_creating_and_submitting_an_itinerary_sends_nothing()
    {
        $itinerary = Itinerary::factory()->draft()->create();

        $this->actingAs($itinerary->user)
            ->post(route('userzone.itineraries.submit', $itinerary))
            ->assertSessionHasNoErrors();

        $this->assertSame(Itinerary::PENDING, $itinerary->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_an_unauthorized_approval_sends_nothing()
    {
        $itinerary = Itinerary::factory()->pending()->create();

        foreach ([$itinerary->user, User::factory()->create()] as $user) {
            $this->actingAs($user)
                ->post(route('userzone.itineraries.approve', $itinerary))
                ->assertForbidden();
        }

        Notification::assertNothingSent();
    }

    public function test_editing_a_published_itinerary_sends_nothing()
    {
        $itinerary = Itinerary::factory()->create();

        $this->actingAs($itinerary->user)
            ->put(route('userzone.itineraries.update', $itinerary), ['title' => 'A renamed route', 'city_id' => $itinerary->city_id])
            ->assertSessionHasNoErrors();

        $this->assertSame(Itinerary::PUBLISHED, $itinerary->fresh()->status);
        Notification::assertNothingSent();
    }
}
