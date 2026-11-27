<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * City and itinerary covers reuse a place photo: an itinerary shows its first photographed stop,
 * otherwise its city's cover; a city shows its first photographed landmark, otherwise any photo.
 */
class CoverImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_itinerary_cover_is_its_first_photographed_stop_in_stop_order()
    {
        $city = City::factory()->create();
        $unphotographed = Place::factory()->for($city)->create(['name' => 'A first stop without a photo']);
        $second = $this->photographed($city, 'Second stop');
        $third = $this->photographed($city, 'Third stop');
        $itinerary = Itinerary::factory()->for($city)->create(['status' => Itinerary::PUBLISHED]);
        $itinerary->syncStops([$unphotographed->id, $second->id, $third->id]);

        $this->get(route('itineraries.show', $itinerary))
            ->assertInertia(fn (Assert $page) => $page
                ->where('itinerary.cover.place', ['id' => $second->id, 'name' => 'Second stop'])
                // The cover conversion, never the original upload.
                ->where('itinerary.cover.url', fn (string $url) => str_contains($url, '/conversions/') && str_ends_with($url, '-cover.jpg')));

        // The order of the stops decides, not the order the places were created in.
        $itinerary->syncStops([$unphotographed->id, $third->id, $second->id]);

        $this->get(route('itineraries.index'))
            ->assertInertia(fn (Assert $page) => $page->where('itineraries.0.cover.place.name', 'Third stop'));
    }

    public function test_without_a_photographed_stop_an_itinerary_shows_its_city_cover_which_prefers_a_landmark()
    {
        $city = City::factory()->create();
        $this->photographed($city, 'Alpha Street');
        $this->photographed($city, 'Zeta Tower', ['landmark']);
        Place::factory()->for($city)->create(['name' => 'Beta Gate', 'themes' => ['landmark']]);
        $itinerary = Itinerary::factory()->for($city)->create(['status' => Itinerary::PUBLISHED]);
        $itinerary->syncStops([Place::where('name', 'Beta Gate')->value('id')]);

        $this->get(route('cities.show', $city))
            ->assertInertia(fn (Assert $page) => $page
                ->where('city.cover.place.name', 'Zeta Tower')
                ->where('itineraries.0.cover.place.name', 'Zeta Tower'));
        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('itineraries.0.cover.place.name', 'Zeta Tower'));
    }

    public function test_without_any_photo_the_cover_is_null()
    {
        $city = City::factory()->create();
        Itinerary::factory()->for($city)->hasAttached(Place::factory()->for($city))->create(['status' => Itinerary::PUBLISHED]);

        $this->get(route('cities.index'))
            ->assertInertia(fn (Assert $page) => $page->where('cities.0.cover', null));
        $this->get(route('itineraries.index'))
            ->assertInertia(fn (Assert $page) => $page->where('itineraries.0.cover', null));
    }

    public function test_replacing_a_stop_photo_refreshes_the_cached_welcome_cover()
    {
        $admin = User::factory()->admin()->create();
        $city = City::factory()->create();
        $place = $this->photographed($city, 'Old Bridge');
        Itinerary::factory()->for($city)->hasAttached($place)->create(['status' => Itinerary::PUBLISHED]);
        $before = Itinerary::popularForWelcome()['itineraries'][0]['cover']['url'];

        $this->actingAs($admin)
            ->put(route('userzone.places.update', $place), [
                'name' => 'Old Bridge',
                'description' => $place->description,
                'city_id' => $city->id,
                'latitude' => $place->latitude,
                'longitude' => $place->longitude,
                'image' => UploadedFile::fake()->image('new.jpg', 400, 300),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));

        $after = Itinerary::popularForWelcome()['itineraries'][0]['cover']['url'];
        $this->assertNotSame($before, $after);
        $this->assertSame($place->fresh()->coverImage()['url'], $after);
    }

    private function photographed(City $city, string $name, array $themes = []): Place
    {
        $place = Place::factory()->for($city)->create(['name' => $name, 'themes' => $themes]);
        $place->addMedia(UploadedFile::fake()->image("{$name}.jpg", 400, 300))->toMediaCollection('images');

        return $place;
    }
}
