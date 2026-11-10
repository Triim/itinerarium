<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Seeder;

/** An editor account with sample author routes and editorial collections. */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $editor = User::firstOrCreate(
            ['email' => 'editor@itinerarium.test'],
            ['name' => 'Редакция Itinerarium', 'password' => 'password', 'email_verified_at' => now()],
        );

        $routes = [
            ['saint-petersburg', 'Классический Петербург за день', 'Главные музеи и соборы центра.', ['Эрмитаж', 'Дворцовая площадь', 'Спас на Крови', 'Исаакиевский собор', 'Развод Дворцового моста']],
            ['saint-petersburg', 'Неформальный Петербург', 'Котельные, арт-центры и пышки.', ['Котельная «Камчатка»', 'Пышечная на Большой Конюшенной', 'Пушкинская, 10']],
            ['rome', 'Рим в кино', 'По следам Феллини и «Римских каникул».', ['Уста истины', 'Фонтан Треви', 'Испанская лестница']],
            ['rome', 'Вечерний Рим', 'Античность днём, панорама на закате.', ['Колизей', 'Рынок Тестаччо', 'Замочная скважина Мальтийского ордена', 'Закат на холме Джаниколо']],
            ['tbilisi', 'Тбилиси для первого визита', 'Старый город и бани.', ['Мост Мира', 'Крепость Нарикала', 'Серные бани Абанотубани', 'Блошиный рынок «Сухой мост»']],
        ];

        $created = [];
        foreach ($routes as [$citySlug, $title, $description, $stops]) {
            $city = City::where('slug', $citySlug)->firstOrFail();
            $itinerary = $editor->itineraries()->updateOrCreate(
                ['title' => $title],
                ['city_id' => $city->id, 'description' => $description, 'is_published' => true],
            );
            $ids = collect($stops)->map(fn ($name) => Place::where('city_id', $city->id)->where('name', $name)->value('id'))->filter()->all();
            $itinerary->syncStops($ids);
            $created[$title] = $itinerary->id;
        }

        $selections = [
            ['first-time', 'Первый раз в городе', null, 'Маршруты, с которых стоит начать знакомство с городом.', ['Классический Петербург за день', 'Тбилиси для первого визита', 'Вечерний Рим']],
            ['cinema', 'Кинотуризм', null, 'Пройти по местам любимых фильмов.', ['Рим в кино']],
            ['petersburg-locals', 'Петербург как местный', 'saint-petersburg', 'Без очередей и экскурсионных автобусов.', ['Неформальный Петербург']],
        ];

        foreach ($selections as [$slug, $title, $citySlug, $description, $titles]) {
            $selection = $editor->selections()->updateOrCreate(
                ['slug' => $slug],
                ['title' => $title, 'description' => $description, 'city_id' => $citySlug ? City::where('slug', $citySlug)->value('id') : null],
            );
            $selection->itineraries()->sync(
                collect($titles)->mapWithKeys(fn ($t, $i) => [$created[$t] => ['position' => $i + 1]])->all()
            );
        }
    }
}
