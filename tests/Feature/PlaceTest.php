<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_city_has_many_places()
    {
        $city = City::factory()->create();
        $places = Place::factory()->count(2)->for($city)->create();
        Place::factory()->create();

        $this->assertEqualsCanonicalizing($places->modelKeys(), $city->places->modelKeys());
        $this->assertTrue($places->first()->fresh()->city->is($city));
    }

    public function test_itineraries_and_places_belong_to_many_of_each_other()
    {
        $city = City::factory()->create();
        $itineraries = Itinerary::factory()->count(2)->for($city)->create();
        $places = Place::factory()->count(2)->for($city)->create();

        $itineraries[0]->places()->attach($places);
        $itineraries[1]->places()->attach($places[0]);

        $this->assertEqualsCanonicalizing($places->modelKeys(), $itineraries[0]->places->modelKeys());
        $this->assertEqualsCanonicalizing($itineraries->modelKeys(), $places[0]->itineraries->modelKeys());
        $this->assertEquals([$itineraries[0]->id], $places[1]->itineraries->modelKeys());
    }

    public function test_coordinates_keep_their_precision()
    {
        $place = Place::factory()->create(['latitude' => -13.743689, 'longitude' => 100.488919])->fresh();

        $this->assertSame(-13.743689, $place->latitude);
        $this->assertSame(100.488919, $place->longitude);
    }

    public function test_a_place_requires_an_existing_city()
    {
        $this->expectException(QueryException::class);

        Place::factory()->create(['city_id' => 999]);
    }

    public function test_deleting_an_itinerary_keeps_its_places()
    {
        $itinerary = Itinerary::factory()->hasAttached(Place::factory())->create();

        $itinerary->delete();

        $this->assertDatabaseEmpty('itinerary_place');
        $this->assertDatabaseCount('places', 1);
    }

    public function test_deleting_a_place_keeps_its_itineraries()
    {
        $itinerary = Itinerary::factory()->hasAttached(Place::factory())->create();

        $itinerary->places->first()->delete();

        $this->assertDatabaseEmpty('itinerary_place');
        $this->assertModelExists($itinerary);
    }

    public function test_seeder_creates_the_expected_places_for_each_city_and_itinerary()
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        // The real travel data seeded after the samples is covered by RealTravelSeederTest.
        $this->assertSame([
            'Barcelona' => ['Sagrada Família', 'Park Güell', 'Casa Batlló', 'Barcelona Cathedral'],
            'Bangkok' => ['Grand Palace', 'Wat Pho', 'Wat Arun'],
        ], City::with('places')->orderBy('id')->take(2)->get()
            ->mapWithKeys(fn (City $city) => [$city->name => $city->places->sortBy('id')->pluck('name')->all()])
            ->all());

        $this->assertSame([
            'Sample: A Weekend in Barcelona' => ['Sagrada Família', 'Park Güell', 'Casa Batlló'],
            'Sample: Barcelona on Foot' => ['Casa Batlló', 'Barcelona Cathedral'],
            'Sample: First Day in Bangkok' => ['Grand Palace', 'Wat Pho', 'Wat Arun'],
        ], Itinerary::with('places')->orderBy('id')->take(3)->get()
            ->mapWithKeys(fn (Itinerary $itinerary) => [$itinerary->title => $itinerary->places->sortBy('id')->pluck('name')->all()])
            ->all());
    }

    public function test_seeded_itineraries_only_contain_places_from_their_own_city()
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        foreach (Itinerary::with('places')->get() as $itinerary) {
            $this->assertSame([$itinerary->city_id], $itinerary->places->pluck('city_id')->unique()->values()->all());
        }
    }
}
