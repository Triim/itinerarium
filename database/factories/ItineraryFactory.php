<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Itinerary>
 */
class ItineraryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'user_id' => User::factory(),
            'city_id' => City::factory(),
            // Factory itineraries stand for existing public content; draft() and pending() model the workflow.
            'status' => Itinerary::PUBLISHED,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => Itinerary::DRAFT]);
    }

    public function pending(): static
    {
        return $this->state(['status' => Itinerary::PENDING]);
    }
}
