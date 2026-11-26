<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueryEfficiencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_query_counts_do_not_grow_with_related_records()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $city = City::factory()->create();
        $place = $this->placeWithImage($city);
        $itinerary = Itinerary::factory()->for($city)->for($admin)->hasAttached($place)->create();

        $urls = [
            '/',
            '/itineraries',
            "/itineraries/{$itinerary->id}",
            '/cities',
            "/cities/{$city->id}",
            '/places',
            "/places/{$place->id}",
            '/user/itineraries',
            '/user/cities',
            '/user/places',
        ];

        $before = array_combine($urls, array_map(fn ($url) => $this->countQueries($url, $admin), $urls));

        // Spread the new records over several cities and authors, with an image for every place.
        foreach (range(1, 20) as $i) {
            $otherCity = City::factory()->create();
            $this->placeWithImage($i % 2 ? $city : $otherCity)->itineraries()->attach($itinerary);
            Itinerary::factory()->for($i % 2 ? $city : $otherCity)->hasAttached($place)->create();
        }

        $after = array_combine($urls, array_map(fn ($url) => $this->countQueries($url, $admin), $urls));

        $this->assertSame($before, $after);
    }

    public function test_home_page_shares_only_the_featured_itinerary_fields_it_displays()
    {
        $itinerary = Itinerary::factory()->create();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->has('itinerary', fn (Assert $prop) => $prop
                    ->where('id', $itinerary->id)
                    ->where('title', $itinerary->title)
                    ->where('city_id', $itinerary->city_id)
                    ->where('user_id', $itinerary->user_id)
                    ->where('city', ['id' => $itinerary->city_id, 'name' => $itinerary->city->name])
                    ->where('user', ['id' => $itinerary->user_id, 'name' => $itinerary->user->name])));
    }

    private function placeWithImage(City $city): Place
    {
        $place = Place::factory()->for($city)->create();
        $place->addMedia(UploadedFile::fake()->image('place.jpg', 400, 300))->toMediaCollection('images');

        return $place;
    }

    private function countQueries(string $url, User $admin): int
    {
        $request = str_starts_with($url, '/user/') ? $this->actingAs($admin) : $this;

        DB::flushQueryLog();
        DB::enableQueryLog();
        $request->get($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
