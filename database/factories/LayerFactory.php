<?php

namespace Database\Factories;

use App\Models\Layer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Layer> */
class LayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'color' => fake()->hexColor(),
        ];
    }
}
