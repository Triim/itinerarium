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

    public function test_approving_a_pending_itinerary_emails_only_its_author()
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
                && $mail->actionUrl === url("/itineraries/three-days-in-lisbon-{$itinerary->id}");
        });
    }

    public function test_rejected_approvals_send_nothing()
    {
        $draft = Itinerary::factory()->draft()->create();
        $pending = Itinerary::factory()->pending()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('userzone.itineraries.approve', $draft))->assertSessionHasErrors('status');
        foreach ([$pending->user, User::factory()->create()] as $user) {
            $this->actingAs($user)->post(route('userzone.itineraries.approve', $pending))->assertForbidden();
        }
        Notification::assertNothingSent();

        // Approving twice: the second, invalid transition sends no second email.
        $this->actingAs($admin)->post(route('userzone.itineraries.approve', $pending))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('userzone.itineraries.approve', $pending))->assertSessionHasErrors('status');
        Notification::assertSentToTimes($pending->user, ItineraryPublished::class, 1);
    }

    public function test_submitting_or_editing_an_itinerary_sends_nothing()
    {
        $draft = Itinerary::factory()->draft()->create();
        $published = Itinerary::factory()->create();

        $this->actingAs($draft->user)
            ->post(route('userzone.itineraries.submit', $draft))
            ->assertSessionHasNoErrors();
        $this->actingAs($published->user)
            ->put(route('userzone.itineraries.update', $published), ['title' => 'A renamed route', 'city_id' => $published->city_id])
            ->assertSessionHasNoErrors();

        $this->assertSame(Itinerary::PENDING, $draft->fresh()->status);
        $this->assertSame(Itinerary::PUBLISHED, $published->fresh()->status);
        Notification::assertNothingSent();
    }
}
