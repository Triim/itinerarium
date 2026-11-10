<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Layer;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Place> */
class PlaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            'layer_id' => Layer::factory(),
            'name' => fake()->streetName(),
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
        ];
    }
}
