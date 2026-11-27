<?php

namespace Tests\Feature;

use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Users saving itineraries to their collection (itinerary_user), separate from authorship.
 */
class SavedItineraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_save_multiple_itineraries()
    {
        $user = User::factory()->create();
        $itineraries = Itinerary::factory()->count(2)->create();
        Itinerary::factory()->create();

        $user->savedItineraries()->attach($itineraries);

        $this->assertEqualsCanonicalizing($itineraries->modelKeys(), $user->savedItineraries->modelKeys());
        $this->assertNotNull($user->savedItineraries->first()->pivot->created_at);
    }

    public function test_an_itinerary_can_be_saved_by_multiple_users()
    {
        $itinerary = Itinerary::factory()->create();
        $users = User::factory()->count(2)->create();

        $itinerary->savedByUsers()->attach($users);

        $this->assertEqualsCanonicalizing($users->modelKeys(), $itinerary->savedByUsers->modelKeys());
        $this->assertSame(2, $itinerary->savedByUsers()->count());
    }

    public function test_the_database_rejects_saving_the_same_itinerary_twice()
    {
        $user = User::factory()->create();
        $itinerary = Itinerary::factory()->create();
        $user->savedItineraries()->attach($itinerary);

        // syncWithoutDetaching is the idempotent way to save; it keeps the single row.
        $user->savedItineraries()->syncWithoutDetaching([$itinerary->id]);
        $this->assertDatabaseCount('itinerary_user', 1);

        $this->expectException(UniqueConstraintViolationException::class);
        $user->savedItineraries()->attach($itinerary);
    }

    public function test_deleting_a_user_removes_only_their_saves()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $itinerary = Itinerary::factory()->create();
        $itinerary->savedByUsers()->attach([$user->id, $other->id]);

        $user->delete();

        $this->assertModelExists($itinerary);
        $this->assertEquals([$other->id], $itinerary->savedByUsers()->pluck('users.id')->all());
    }

    public function test_deleting_an_itinerary_removes_only_its_saves()
    {
        $user = User::factory()->create();
        [$deleted, $kept] = Itinerary::factory()->count(2)->create();
        $user->savedItineraries()->attach([$deleted->id, $kept->id]);

        $deleted->delete();

        $this->assertModelExists($user);
        $this->assertEquals([$kept->id], $user->savedItineraries()->pluck('itineraries.id')->all());
    }

    public function test_authorship_and_saves_are_independent()
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        $authored = Itinerary::factory()->for($author)->hasAttached(Place::factory())->create();
        $other = Itinerary::factory()->create();

        $reader->savedItineraries()->attach($authored);
        $author->savedItineraries()->attach($other);

        $this->assertEquals([$authored->id], $author->itineraries->modelKeys());
        $this->assertEquals([$other->id], $author->savedItineraries->modelKeys());
        $this->assertEmpty($reader->itineraries);
        $this->assertTrue($authored->fresh()->user->is($author));
        $this->assertEquals([$reader->id], $authored->savedByUsers->modelKeys());
        $this->assertCount(1, $authored->places);

        // Removing a save leaves the authorship and the stops alone.
        $reader->savedItineraries()->detach($authored);
        $this->assertTrue($authored->fresh()->user->is($author));
        $this->assertCount(1, $authored->fresh()->places);
    }
}
