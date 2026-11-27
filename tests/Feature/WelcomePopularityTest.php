<?php

namespace Tests\Feature;

use App\Models\Itinerary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Welcome cards are ranked by how many users saved them (itinerary_user), newest first on a tie.
 */
class WelcomePopularityTest extends TestCase
{
    use RefreshDatabase;

    public function test_more_saves_rank_higher_and_unsaved_itineraries_come_last()
    {
        // The unsaved one is the newest, so only the saves can put it last.
        $unsaved = Itinerary::factory()->create();
        $once = Itinerary::factory()->create(['created_at' => now()->subDays(2)]);
        $twice = Itinerary::factory()->create(['created_at' => now()->subDays(3)]);
        $this->saveBy($once, 1);
        $this->saveBy($twice, 2);

        $this->assertSame([$twice->id, $once->id, $unsaved->id], $this->ids());
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('popular', true));
    }

    public function test_draft_and_pending_itineraries_are_left_out_however_often_they_are_saved()
    {
        $published = Itinerary::factory()->create();
        $draft = Itinerary::factory()->draft()->create();
        $pending = Itinerary::factory()->pending()->create();
        // Saving is refused for unpublished itineraries, but rows from before still must not count.
        $this->saveBy($draft, 3);
        $this->saveBy($pending, 3);

        $this->assertSame([$published->id], $this->ids());
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('popular', false));
    }

    public function test_equal_saves_are_ordered_newest_first_then_by_id()
    {
        $older = Itinerary::factory()->create(['created_at' => now()->subDays(2)]);
        $sameTimeLow = Itinerary::factory()->create(['created_at' => now()->subDay()]);
        $sameTimeHigh = Itinerary::factory()->create(['created_at' => $sameTimeLow->created_at]);
        foreach ([$older, $sameTimeLow, $sameTimeHigh] as $itinerary) {
            $this->saveBy($itinerary, 2);
        }

        $this->assertSame([$sameTimeHigh->id, $sameTimeLow->id, $older->id], $this->ids());
    }

    public function test_only_the_three_most_saved_are_shown()
    {
        $itineraries = Itinerary::factory()->count(5)->create();
        foreach ($itineraries as $index => $itinerary) {
            $this->saveBy($itinerary, $index + 1);
        }

        $this->assertSame($itineraries->reverse()->take(3)->pluck('id')->all(), $this->ids());
    }

    public function test_without_any_saves_the_newest_itineraries_are_shown()
    {
        Itinerary::factory()->create(['created_at' => now()->subDays(5)]);
        $newest = Itinerary::factory()->count(3)->sequence(
            ['created_at' => now()->subDays(3)],
            ['created_at' => now()->subDays(2)],
            ['created_at' => now()->subDay()],
        )->create();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('popular', false)
                ->where('itineraries', fn ($cards) => collect($cards)->pluck('id')->all() === $newest->reverse()->pluck('id')->all()));
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_saves()
    {
        $itineraries = Itinerary::factory()->count(3)->create();
        $this->saveBy($itineraries[0], 1);
        $before = $this->countQueries();

        foreach ($itineraries as $itinerary) {
            $this->saveBy($itinerary, 10);
        }

        $this->assertSame($before, $this->countQueries());
    }

    private function saveBy(Itinerary $itinerary, int $users): void
    {
        $itinerary->savedByUsers()->attach(User::factory()->count($users)->create());
    }

    private function ids(): array
    {
        $ids = null;
        $this->get('/')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$ids) {
                $ids = array_column($page->toArray()['props']['itineraries'], 'id');
            });

        return $ids;
    }

    private function countQueries(): int
    {
        // Measured cold, so the cache cannot hide queries.
        Itinerary::forgetWelcomeCache();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
