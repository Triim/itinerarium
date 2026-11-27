<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The City, Place and Itinerary relationships and the database constraints behind them.
 */
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

    public function test_deleting_an_itinerary_or_a_place_only_removes_their_links()
    {
        $kept = Itinerary::factory()->hasAttached(Place::factory())->create();
        $deleted = Itinerary::factory()->hasAttached(Place::factory())->create();
        $placeOfDeleted = $deleted->places->first();

        $deleted->delete();
        $kept->places->first()->delete();

        $this->assertDatabaseEmpty('itinerary_place');
        $this->assertModelExists($kept);
        $this->assertModelExists($placeOfDeleted);
    }
}
