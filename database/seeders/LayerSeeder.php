<?php

namespace Database\Seeders;

use App\Models\Layer;
use Illuminate\Database\Seeder;

class LayerSeeder extends Seeder
{
    public function run(): void
    {
        $layers = [
            ['slug' => 'tourist', 'name' => 'Туристические места', 'color' => '#2563eb', 'description' => 'Главные достопримечательности, которые стоит увидеть.'],
            ['slug' => 'filming', 'name' => 'Локации съёмок', 'color' => '#9333ea', 'description' => 'Места, где снимали известные фильмы.'],
            ['slug' => 'informal', 'name' => 'Неформальные достопримечательности', 'color' => '#ea580c', 'description' => 'То, чего нет в путеводителях: дворы, арт-пространства, легенды.'],
            ['slug' => 'food', 'name' => 'Worth to try', 'color' => '#16a34a', 'description' => 'Местная еда, которую стоит попробовать.'],
            ['slug' => 'experience', 'name' => 'Unique experience', 'color' => '#db2777', 'description' => 'Впечатления, которые можно получить только здесь.'],
        ];

        foreach ($layers as $i => $layer) {
            Layer::updateOrCreate(['slug' => $layer['slug']], [...$layer, 'sort_order' => $i + 1]);
        }
    }
}
