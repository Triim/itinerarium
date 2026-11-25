<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
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

        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        $barcelona = City::create(['name' => 'Barcelona', 'user_id' => $user->id]);
        $bangkok = City::create(['name' => 'Bangkok', 'user_id' => $user->id]);

        // Coordinates are taken from Wikidata (property P625).
        $sagradaFamilia = Place::create([
            'name' => 'Sagrada Família',
            'description' => 'Basilica designed by Antoni Gaudí, begun in 1882.',
            'latitude' => 41.403690,
            'longitude' => 2.174330,
            'city_id' => $barcelona->id,
        ]);
        $parkGuell = Place::create([
            'name' => 'Park Güell',
            'description' => 'Hillside public park with architecture and mosaics by Antoni Gaudí.',
            'latitude' => 41.413611,
            'longitude' => 2.152778,
            'city_id' => $barcelona->id,
        ]);
        $casaBatllo = Place::create([
            'name' => 'Casa Batlló',
            'description' => 'House on Passeig de Gràcia remodelled by Antoni Gaudí.',
            'latitude' => 41.391580,
            'longitude' => 2.164920,
            'city_id' => $barcelona->id,
        ]);
        $barcelonaCathedral = Place::create([
            'name' => 'Barcelona Cathedral',
            'description' => 'Gothic cathedral in the old town, dedicated to the Holy Cross and Saint Eulalia.',
            'latitude' => 41.383889,
            'longitude' => 2.176389,
            'city_id' => $barcelona->id,
        ]);
        $grandPalace = Place::create([
            'name' => 'Grand Palace',
            'description' => 'Royal palace complex, home to the Temple of the Emerald Buddha.',
            'latitude' => 13.750139,
            'longitude' => 100.492028,
            'city_id' => $bangkok->id,
        ]);
        $watPho = Place::create([
            'name' => 'Wat Pho',
            'description' => 'Buddhist temple complex known for its reclining Buddha.',
            'latitude' => 13.746389,
            'longitude' => 100.493611,
            'city_id' => $bangkok->id,
        ]);
        $watArun = Place::create([
            'name' => 'Wat Arun',
            'description' => 'Riverside Buddhist temple on the west bank of the Chao Phraya.',
            'latitude' => 13.743689,
            'longitude' => 100.488919,
            'city_id' => $bangkok->id,
        ]);

        $barcelonaWeekend = Itinerary::create(['title' => 'Sample: A Weekend in Barcelona', 'user_id' => $user->id, 'city_id' => $barcelona->id]);
        $barcelonaOnFoot = Itinerary::create(['title' => 'Sample: Barcelona on Foot', 'user_id' => $user->id, 'city_id' => $barcelona->id]);
        $bangkokFirstDay = Itinerary::create(['title' => 'Sample: First Day in Bangkok', 'user_id' => $user->id, 'city_id' => $bangkok->id]);

        $barcelonaWeekend->places()->attach([$sagradaFamilia->id, $parkGuell->id, $casaBatllo->id]);
        $barcelonaOnFoot->places()->attach([$casaBatllo->id, $barcelonaCathedral->id]);
        $bangkokFirstDay->places()->attach([$grandPalace->id, $watPho->id, $watArun->id]);
    }
}
