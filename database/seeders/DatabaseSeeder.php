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
     *
     * Every sample record is made by its model factory. The explicit values override the
     * factory defaults, and for() sets each parent, so no extra user or city is created.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // The password is set here rather than left to the factory default, so the login is visible in the seeder.
        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $barcelona = City::factory()->for($user)->create(['name' => 'Barcelona']);
        $bangkok = City::factory()->for($user)->create(['name' => 'Bangkok']);

        // Coordinates are taken from Wikidata (property P625).
        $sagradaFamilia = Place::factory()->for($barcelona)->create([
            'name' => 'Sagrada Família',
            'description' => 'Basilica designed by Antoni Gaudí, begun in 1882.',
            'latitude' => 41.403690,
            'longitude' => 2.174330,
        ]);
        $parkGuell = Place::factory()->for($barcelona)->create([
            'name' => 'Park Güell',
            'description' => 'Hillside public park with architecture and mosaics by Antoni Gaudí.',
            'latitude' => 41.413611,
            'longitude' => 2.152778,
        ]);
        $casaBatllo = Place::factory()->for($barcelona)->create([
            'name' => 'Casa Batlló',
            'description' => 'House on Passeig de Gràcia remodelled by Antoni Gaudí.',
            'latitude' => 41.391580,
            'longitude' => 2.164920,
        ]);
        $barcelonaCathedral = Place::factory()->for($barcelona)->create([
            'name' => 'Barcelona Cathedral',
            'description' => 'Gothic cathedral in the old town, dedicated to the Holy Cross and Saint Eulalia.',
            'latitude' => 41.383889,
            'longitude' => 2.176389,
        ]);
        $grandPalace = Place::factory()->for($bangkok)->create([
            'name' => 'Grand Palace',
            'description' => 'Royal palace complex, home to the Temple of the Emerald Buddha.',
            'latitude' => 13.750139,
            'longitude' => 100.492028,
        ]);
        $watPho = Place::factory()->for($bangkok)->create([
            'name' => 'Wat Pho',
            'description' => 'Buddhist temple complex known for its reclining Buddha.',
            'latitude' => 13.746389,
            'longitude' => 100.493611,
        ]);
        $watArun = Place::factory()->for($bangkok)->create([
            'name' => 'Wat Arun',
            'description' => 'Riverside Buddhist temple on the west bank of the Chao Phraya.',
            'latitude' => 13.743689,
            'longitude' => 100.488919,
        ]);

        $barcelonaWeekend = Itinerary::factory()->for($user)->for($barcelona)->create(['title' => 'Sample: A Weekend in Barcelona', 'status' => Itinerary::PUBLISHED]);
        $barcelonaOnFoot = Itinerary::factory()->for($user)->for($barcelona)->create(['title' => 'Sample: Barcelona on Foot', 'status' => Itinerary::PUBLISHED]);
        $bangkokFirstDay = Itinerary::factory()->for($user)->for($bangkok)->create(['title' => 'Sample: First Day in Bangkok', 'status' => Itinerary::PUBLISHED]);

        $barcelonaWeekend->places()->attach([$sagradaFamilia->id, $parkGuell->id, $casaBatllo->id]);
        $barcelonaOnFoot->places()->attach([$casaBatllo->id, $barcelonaCathedral->id]);
        $bangkokFirstDay->places()->attach([$grandPalace->id, $watPho->id, $watArun->id]);

        // Real routes come after the samples, so they are the newest itineraries on the welcome page.
        $this->call(RealTravelSeeder::class);
    }
}
