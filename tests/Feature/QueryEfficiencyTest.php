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
            "/places?city={$city->id}",
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

    public function test_home_page_shares_only_the_itinerary_fields_it_displays()
    {
        $itinerary = Itinerary::factory()->hasAttached(Place::factory()->count(2))->create();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->has('itineraries', 1)
                ->has('itineraries.0', fn (Assert $prop) => $prop
                    ->where('id', $itinerary->id)
                    ->where('title', $itinerary->title)
                    ->where('places_count', 2)
                    ->where('city', ['id' => $itinerary->city_id, 'name' => $itinerary->city->name])
                    ->where('user', ['id' => $itinerary->user_id, 'name' => $itinerary->user->name])));
    }

    public function test_itinerary_pages_share_only_the_fields_they_display()
    {
        $itinerary = Itinerary::factory()->hasAttached(Place::factory()->count(2))->create();
        $city = ['id' => $itinerary->city_id, 'name' => $itinerary->city->name];
        $user = ['id' => $itinerary->user_id, 'name' => $itinerary->user->name];

        $this->get('/itineraries')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('itineraries', 1)
                ->has('itineraries.0', fn (Assert $prop) => $prop
                    ->where('id', $itinerary->id)
                    ->where('title', $itinerary->title)
                    ->where('places_count', 2)
                    ->where('city', $city)
                    ->where('user', $user)));

        $this->get("/itineraries/{$itinerary->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('itinerary', fn (Assert $prop) => $prop
                    ->where('id', $itinerary->id)
                    ->where('title', $itinerary->title)
                    ->where('city', $city)
                    ->where('user', $user)
                    ->has('places', 2, fn (Assert $place) => $place
                        ->hasAll(['id', 'name', 'description', 'image_url', 'themes', 'films']))));
    }

    public function test_city_and_place_lists_share_only_the_fields_they_display()
    {
        $city = City::factory()->create();
        $place = $this->placeWithImage($city);
        $place->update(['themes' => ['film', 'history'], 'films' => ['Good Bye, Lenin!']]);
        $itinerary = Itinerary::factory()->for($city)->hasAttached($place)->create();

        $this->get('/cities')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cities.0', fn (Assert $prop) => $prop
                    ->where('id', $city->id)
                    ->where('name', $city->name)
                    ->where('itineraries_count', 1)
                    ->where('places_count', 1)));

        $this->get("/cities/{$city->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('city', fn (Assert $prop) => $prop->hasAll(['id', 'name', 'itineraries_count', 'places_count']))
                ->has('itineraries.0', fn (Assert $prop) => $prop
                    ->hasAll(['id', 'title', 'places_count'])
                    ->where('user', ['id' => $itinerary->user_id, 'name' => $itinerary->user->name])));

        // No coordinates, films, descriptions, media paths or user records in the directory.
        $this->get('/places')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cities.0', fn (Assert $prop) => $prop
                    ->hasAll(['id', 'name'])
                    ->has('places.0', fn (Assert $prop) => $prop
                        ->where('id', $place->id)
                        ->where('name', $place->name)
                        ->where('itineraries_count', 1)
                        ->where('image_url', $place->imageUrl('thumbnail'))
                        ->where('themes', ['film', 'history']))));
    }

    public function test_places_list_query_count_does_not_grow_with_places_themes_or_cities()
    {
        $counts = [];
        foreach ([1, 10, 30] as $total) {
            while (Place::count() < $total) {
                $place = $this->placeWithImage(City::factory()->create());
                $place->update(['themes' => ['film', 'architecture', 'history']]);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/places')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->has('cities', $total));
            $counts[$total] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        $this->assertSame(array_fill_keys([1, 10, 30], $counts[1]), $counts);
    }

    public function test_itinerary_page_query_count_does_not_grow_with_its_places()
    {
        $city = City::factory()->create();
        $itinerary = Itinerary::factory()->for($city)->create();

        $counts = [];
        foreach ([1, 5, 21] as $total) {
            while ($itinerary->places()->count() < $total) {
                $place = $this->placeWithImage($city);
                $place->update(['themes' => ['film', 'history'], 'films' => ['Good Bye, Lenin!', 'Run Lola Run']]);
                $itinerary->places()->attach($place, ['position' => $itinerary->places()->count() + 1]);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get("/itineraries/{$itinerary->id}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->has('itinerary.places', $total));
            $counts[$total] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        $this->assertSame(array_fill_keys([1, 5, 21], $counts[1]), $counts);
    }

    public function test_home_page_query_count_does_not_grow_with_displayed_itineraries()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $city = City::factory()->create();

        $counts = [];
        foreach ([1, 2, 3, 10] as $total) {
            while (Itinerary::count() < $total) {
                Itinerary::factory()
                    ->for(City::factory())
                    ->hasAttached(Place::factory()->count(3)->for($city))
                    ->create();
            }

            $counts[$total] = [
                'guest' => $this->countHomeQueries(null, min($total, 3)),
                'user' => $this->countHomeQueries($admin, min($total, 3)),
            ];
        }

        // One query for the itineraries with their place counts, one each for their cities and authors.
        $this->assertSame(3, $counts[1]['guest']);
        $this->assertSame(array_fill_keys([1, 2, 3, 10], $counts[1]), $counts);
    }

    public function test_a_warm_home_page_request_skips_the_itinerary_queries()
    {
        $viewer = User::factory()->create();
        Itinerary::factory()->count(3)->hasAttached(Place::factory()->count(2))->create();

        foreach ([null, $viewer] as $user) {
            $cold = $this->homeQueries($user, true);
            $warm = $this->homeQueries($user, false);

            $this->assertNotEmpty($this->welcomeQueries($cold));
            $this->assertSame([], $this->welcomeQueries($warm));
        }
    }

    private function placeWithImage(City $city): Place
    {
        $place = Place::factory()->for($city)->create();
        $place->addMedia(UploadedFile::fake()->image('place.jpg', 400, 300))->toMediaCollection('images');

        return $place;
    }

    private function countHomeQueries(?User $user, int $expectedCards): int
    {
        return count($this->homeQueries($user, true, $expectedCards));
    }

    /**
     * Query log of one home page request; a cold request starts with an empty Welcome cache.
     */
    private function homeQueries(?User $user, bool $cold, int $expectedCards = 3): array
    {
        if ($cold) {
            Itinerary::forgetWelcomeCache();
        }

        $this->app['auth']->forgetGuards();
        $request = $user ? $this->actingAs($user) : $this;

        DB::flushQueryLog();
        DB::enableQueryLog();
        $request->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user', fn ($authUser) => $authUser?->offsetGet('id') === $user?->id)
                ->has('itineraries', $expectedCards));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return $queries;
    }

    /**
     * The queries that build the Welcome cards: itineraries with place counts, cities and authors.
     */
    private function welcomeQueries(array $queries): array
    {
        return array_values(array_filter(
            array_column($queries, 'query'),
            fn ($sql) => preg_match('/from "(itineraries|cities|users)"/', $sql),
        ));
    }

    private function countQueries(string $url, User $admin): int
    {
        // Measure the home page cold, so the cache cannot hide queries that grow with the data.
        Itinerary::forgetWelcomeCache();
        $request = str_starts_with($url, '/user/') ? $this->actingAs($admin) : $this;

        DB::flushQueryLog();
        DB::enableQueryLog();
        $request->get($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
