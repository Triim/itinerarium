<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $barcelona = City::create(['name' => 'Barcelona', 'user_id' => $user->id]);
        $bangkok = City::create(['name' => 'Bangkok', 'user_id' => $user->id]);

        Itinerary::create(['title' => 'Sample: A Weekend in Barcelona', 'user_id' => $user->id, 'city_id' => $barcelona->id]);
        Itinerary::create(['title' => 'Sample: Barcelona on Foot', 'user_id' => $user->id, 'city_id' => $barcelona->id]);
        Itinerary::create(['title' => 'Sample: First Day in Bangkok', 'user_id' => $user->id, 'city_id' => $bangkok->id]);
    }
}
