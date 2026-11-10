<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Layer;
use Illuminate\Database\Seeder;

/**
 * Prototype content: a limited list of cities with editorial points.
 * Coordinates are approximate demo data — verify before publishing.
 */
class CitySeeder extends Seeder
{
    public function run(): void
    {
        $layers = Layer::pluck('id', 'slug');

        foreach ($this->cities() as $data) {
            $city = City::updateOrCreate(['slug' => $data['slug']], collect($data)->except('places')->all());

            foreach ($data['places'] as [$layer, $name, $lat, $lng, $description]) {
                $city->places()->updateOrCreate(
                    ['name' => $name],
                    ['layer_id' => $layers[$layer], 'lat' => $lat, 'lng' => $lng, 'description' => $description],
                );
            }
        }
    }

    private function cities(): array
    {
        return [
            [
                'slug' => 'saint-petersburg', 'name' => 'Saint Petersburg', 'country' => 'Russia',
                'lat' => 59.9386, 'lng' => 30.3141, 'zoom' => 13,
                'description' => 'Palaces, canals, hidden courtyards and white nights.',
                'places' => [
                    ['tourist', 'The Hermitage', 59.9398, 30.3146, 'One of the largest art museums in the world, housed in the Winter Palace.'],
                    ['tourist', "St. Isaac's Cathedral", 59.9343, 30.3061, 'The colonnade offers a panorama of the city centre.'],
                    ['tourist', 'Church of the Savior on Spilled Blood', 59.9400, 30.3289, 'Mosaic-covered church on the Griboyedov Canal.'],
                    ['tourist', 'Peter and Paul Fortress', 59.9500, 30.3167, 'Where the city was founded.'],
                    ['filming', 'Palace Square', 59.9391, 30.3156, 'Eisenstein filmed "October" (1927) here.'],
                    ['filming', 'Smolensky Lutheran Cemetery', 59.9475, 30.2505, 'A location from the film "Brother" (1997).'],
                    ['informal', 'Pushkinskaya 10', 59.9306, 30.3586, "An art centre in a former apartment block, an icon of the city's underground scene."],
                    ['informal', 'Kamchatka Boiler Room', 59.9567, 30.2925, 'The boiler room where Viktor Tsoi worked.'],
                    ['food', 'Pyshechnaya on Bolshaya Konyushennaya', 59.9370, 30.3240, 'Sugar-dusted doughnuts and milky coffee from the urn.'],
                    ['experience', 'Palace Bridge opening', 59.9413, 30.3086, 'Watch the bridges open at night during the navigation season.'],
                ],
            ],
            [
                'slug' => 'rome', 'name' => 'Rome', 'country' => 'Italy',
                'lat' => 41.8960, 'lng' => 12.4820, 'zoom' => 14,
                'description' => 'The Eternal City: antiquity, baroque and cinema.',
                'places' => [
                    ['tourist', 'Colosseum', 41.8902, 12.4922, 'The Flavian Amphitheatre.'],
                    ['tourist', 'Pantheon', 41.8986, 12.4769, 'Temple of all gods with an open oculus.'],
                    ['filming', 'Trevi Fountain', 41.9009, 12.4833, "Anita Ekberg's scene in Fellini's \"La Dolce Vita\"."],
                    ['filming', 'Spanish Steps', 41.9059, 12.4823, 'Audrey Hepburn eats gelato in "Roman Holiday".'],
                    ['filming', 'Mouth of Truth', 41.8881, 12.4814, 'The famous scene from "Roman Holiday".'],
                    ['informal', 'Aventine Keyhole', 41.8833, 12.4783, "St. Peter's dome framed through the Knights of Malta keyhole."],
                    ['food', 'Testaccio Market', 41.8766, 12.4757, 'Roman street food: supplì and trapizzino.'],
                    ['experience', 'Sunset on the Janiculum', 41.8919, 12.4614, 'The best panorama of Rome at sunset.'],
                ],
            ],
            [
                'slug' => 'tbilisi', 'name' => 'Tbilisi', 'country' => 'Georgia',
                'lat' => 41.6938, 'lng' => 44.8015, 'zoom' => 14,
                'description' => 'Old Town, sulphur baths and famous hospitality.',
                'places' => [
                    ['tourist', 'Narikala Fortress', 41.6880, 44.8086, 'Ancient fortress above the Old Town.'],
                    ['tourist', 'Bridge of Peace', 41.6930, 44.8086, 'Glass pedestrian bridge over the Kura river.'],
                    ['informal', 'Fabrika', 41.7096, 44.8030, 'A former sewing factory turned creative hub.'],
                    ['informal', 'Dry Bridge flea market', 41.7006, 44.8030, 'Soviet vintage, paintings and everything else.'],
                    ['food', 'Dezerter Bazaar', 41.7215, 44.7945, 'Churchkhela, spices, cheese and khachapuri.'],
                    ['experience', 'Abanotubani sulphur baths', 41.6880, 44.8110, 'Hot-spring baths where the city began.'],
                ],
            ],
        ];
    }
}
