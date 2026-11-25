<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Sagrada Família',
            'description' => 'Basilica designed by Antoni Gaudí.',
            'latitude' => 41.403690,
            'longitude' => 2.174330,
            'city_id' => City::factory()->state(['name' => 'Barcelona']),
        ];
    }
}
