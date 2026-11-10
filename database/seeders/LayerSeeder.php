<?php

namespace Database\Seeders;

use App\Models\Layer;
use Illuminate\Database\Seeder;

class LayerSeeder extends Seeder
{
    public function run(): void
    {
        $layers = [
            ['slug' => 'tourist', 'name' => 'Tourist sights', 'color' => '#2563eb', 'description' => 'The must-see landmarks.'],
            ['slug' => 'filming', 'name' => 'Filming locations', 'color' => '#9333ea', 'description' => 'Places where famous films were shot.'],
            ['slug' => 'informal', 'name' => 'Informal sights', 'color' => '#ea580c', 'description' => 'What guidebooks miss: courtyards, art spaces, local legends.'],
            ['slug' => 'food', 'name' => 'Worth to try', 'color' => '#16a34a', 'description' => 'Local food you should try.'],
            ['slug' => 'experience', 'name' => 'Unique experience', 'color' => '#db2777', 'description' => 'Experiences you can only have here.'],
        ];

        foreach ($layers as $i => $layer) {
            Layer::updateOrCreate(['slug' => $layer['slug']], [...$layer, 'sort_order' => $i + 1]);
        }
    }
}
