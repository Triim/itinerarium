<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * N+1 protection: the number of queries a page runs must not grow with the records it shows.
 * The counts are compared with themselves, not with fixed numbers, so harmless changes do not break it.
 */
class QueryEfficiencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_counts_do_not_grow_with_related_records()
    {
        $admin = User::factory()->admin()->create();
        $city = City::factory()->create();
        $place = $this->placeWithImage($city);
        $itinerary = Itinerary::factory()->for($city)->for($admin)->hasAttached($place)->create();

        $urls = [
            '/',
            '/itineraries',
            route('itineraries.show', $itinerary),
            '/cities',
            "/cities/{$city->id}",
            '/places',
            "/places?city={$city->id}",
            "/places/{$place->id}",
            '/user/itineraries',
            '/user/cities',
            '/user/places',
        ];

        $before = array_combine($urls, array_map(fn ($url) => $this->countQueries($url, $admin), $urls));

        // Spread the new records over several cities and authors, with an image and themes for every place.
        foreach (range(1, 20) as $i) {
            $otherCity = City::factory()->create();
            $newPlace = $this->placeWithImage($i % 2 ? $city : $otherCity);
            $newPlace->itineraries()->attach($itinerary, ['position' => $i + 1]);
            Itinerary::factory()->for($i % 2 ? $city : $otherCity)->hasAttached($place)->create();
        }

        $after = array_combine($urls, array_map(fn ($url) => $this->countQueries($url, $admin), $urls));

        $this->assertSame($before, $after);
    }

    private function placeWithImage(City $city): Place
    {
        $place = Place::factory()->for($city)->create(['themes' => ['film', 'history'], 'films' => ['Good Bye, Lenin!']]);
        $place->addMedia(UploadedFile::fake()->image('place.jpg', 400, 300))->toMediaCollection('images');

        return $place;
    }

    private function countQueries(string $url, User $admin): int
    {
        // Measure the home page cold, so the cache cannot hide queries that grow with the data.
        Itinerary::forgetWelcomeCache();
        $this->app['auth']->forgetGuards();
        $request = str_starts_with($url, '/user/') ? $this->actingAs($admin) : $this;

        DB::flushQueryLog();
        DB::enableQueryLog();
        $request->get($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
