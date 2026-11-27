<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Itinerary management in the Userzone: owners manage their own itineraries, administrators any.
 * The publication workflow is covered by ItineraryPublicationTest.
 */
class UserzoneItineraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_itinerary_management()
    {
        $itinerary = Itinerary::factory()->create();

        $this->get('/user/itineraries')->assertRedirect('/login');
        $this->get('/user/itineraries/create')->assertRedirect('/login');
        $this->post('/user/itineraries', $this->validData())->assertRedirect('/login');
        $this->get("/user/itineraries/{$itinerary->id}")->assertRedirect('/login');
        $this->get("/user/itineraries/{$itinerary->id}/edit")->assertRedirect('/login');
        $this->put("/user/itineraries/{$itinerary->id}", $this->validData())->assertRedirect('/login');
        $this->delete("/user/itineraries/{$itinerary->id}")->assertRedirect('/login');

        $this->assertDatabaseCount('itineraries', 1);
        $this->assertEquals($itinerary->getAttributes(), $itinerary->fresh()->getAttributes());
    }

    public function test_users_only_see_their_own_itineraries_with_city_and_author()
    {
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $bangkok = City::factory()->create(['name' => 'Bangkok']);
        $ada = User::factory()->create(['name' => 'Ada']);
        $grace = User::factory()->create(['name' => 'Grace']);
        Itinerary::factory()->for($bangkok)->for($ada)->create(['title' => 'Temples']);
        Itinerary::factory()->for($barcelona)->for($ada)->create(['title' => 'Gaudí']);
        Itinerary::factory()->for($barcelona)->for($grace)->create(['title' => 'Markets']);

        $this->actingAs($ada)
            ->get('/user/itineraries')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/itineraries/index')
                ->has('itineraries', 2)
                ->where('itineraries.0.title', 'Gaudí')
                ->where('itineraries.0.city.name', 'Barcelona')
                ->where('itineraries.0.user.name', 'Ada')
                ->missing('itineraries.0.user.email')
                ->where('itineraries.1.title', 'Temples')
                ->where('itineraries.1.city.name', 'Bangkok'));

        $this->actingAs($grace)
            ->get('/user/itineraries')
            ->assertInertia(fn (Assert $page) => $page
                ->has('itineraries', 1)
                ->where('itineraries.0.title', 'Markets'));
    }

    public function test_administrators_see_all_itineraries_with_city_and_author()
    {
        $city = City::factory()->create(['name' => 'Barcelona']);
        $admin = User::factory()->admin()->create(['name' => 'Admin']);
        $ada = User::factory()->create(['name' => 'Ada']);
        Itinerary::factory()->for($city)->for($ada)->create(['title' => 'Temples']);
        Itinerary::factory()->for($city)->for($admin)->create(['title' => 'Gaudí']);
        Itinerary::factory()->for($city)->create(['title' => 'Markets']);

        $this->actingAs($admin)
            ->get('/user/itineraries')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('itineraries', 3)
                ->where('itineraries.0.title', 'Gaudí')
                ->where('itineraries.1.title', 'Markets')
                ->where('itineraries.2.title', 'Temples')
                ->where('itineraries.2.city.name', 'Barcelona')
                ->where('itineraries.2.user.name', 'Ada')
                ->missing('itineraries.2.user.email'));
    }

    public function test_create_page_receives_cities_and_places_ordered_by_name()
    {
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $bangkok = City::factory()->create(['name' => 'Bangkok']);
        $watPho = Place::factory()->for($bangkok)->create(['name' => 'Wat Pho']);
        $casaBatllo = Place::factory()->for($barcelona)->create(['name' => 'Casa Batlló']);

        $this->actingAs(User::factory()->create())
            ->get('/user/itineraries/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/itineraries/create')
                ->where('cities', [
                    ['id' => $bangkok->id, 'name' => 'Bangkok'],
                    ['id' => $barcelona->id, 'name' => 'Barcelona'],
                ])
                ->where('places', [
                    ['id' => $casaBatllo->id, 'name' => 'Casa Batlló', 'city_id' => $barcelona->id],
                    ['id' => $watPho->id, 'name' => 'Wat Pho', 'city_id' => $bangkok->id],
                ]));
    }

    public function test_creating_saves_the_selected_places_as_ordered_stops()
    {
        $user = User::factory()->create();
        $city = City::factory()->create();
        [$a, $b, $c] = $this->placesNamed($city, ['Alpha', 'Bravo', 'Charlie']);

        $this->actingAs($user)
            ->post('/user/itineraries', [
                'title' => 'Two days in town',
                'city_id' => $city->id,
                'place_ids' => [$c->id, $a->id, (string) $b->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.itineraries.index'));

        $itinerary = Itinerary::sole();
        $this->assertSame('Two days in town', $itinerary->title);
        $this->assertSame($city->id, $itinerary->city_id);
        $this->assertSame($user->id, $itinerary->user_id);
        $this->assertStops([$c->id, $a->id, $b->id], $itinerary);
    }

    public function test_the_author_is_always_the_current_user()
    {
        $other = User::factory()->create();

        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)
                ->post('/user/itineraries', $this->validData(['user_id' => $other->id]))
                ->assertSessionHasNoErrors();

            $this->assertSame($user->id, Itinerary::latest('id')->first()->user_id);
        }
    }

    #[DataProvider('emptySelections')]
    public function test_an_itinerary_can_be_saved_without_places(array $selection)
    {
        $this->actingAs(User::factory()->create())
            ->post('/user/itineraries', $this->validData($selection))
            ->assertSessionHasNoErrors();

        $itinerary = Itinerary::sole();
        $this->assertStops([], $itinerary);

        $itinerary->syncStops([Place::factory()->for($itinerary->city)->create()->id]);
        $this->put("/user/itineraries/{$itinerary->id}", $this->validData(['city_id' => $itinerary->city_id, ...$selection]))
            ->assertSessionHasNoErrors();

        $this->assertStops([], $itinerary);
    }

    public static function emptySelections(): array
    {
        return [
            'omitted' => [[]],
            'null' => [['place_ids' => null]],
            'empty array' => [['place_ids' => []]],
        ];
    }

    #[DataProvider('validTitles')]
    public function test_boundary_titles_are_accepted(string $title)
    {
        $this->actingAs(User::factory()->create())
            ->post('/user/itineraries', $this->validData(['title' => $title]))
            ->assertSessionHasNoErrors();

        $this->assertSame($title, Itinerary::sole()->title);
    }

    public static function validTitles(): array
    {
        return [
            'shortest' => ['abc'],
            'longest' => [str_repeat('a', 255)],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected_when_creating_and_updating(string $field, mixed $value, ?string $error = null)
    {
        $itinerary = Itinerary::factory()->create();
        $place = Place::factory()->for($itinerary->city)->create();
        $itinerary->places()->attach($place);

        $this->actingAs($itinerary->user)
            ->from('/user/itineraries/create')
            ->post('/user/itineraries', $this->validData([$field => $value]))
            ->assertRedirect('/user/itineraries/create')
            ->assertSessionHasErrors([$error ?? $field]);
        $this->assertDatabaseCount('itineraries', 1);

        $this->from("/user/itineraries/{$itinerary->id}/edit")
            ->put("/user/itineraries/{$itinerary->id}", $this->validData([$field => $value]))
            ->assertRedirect("/user/itineraries/{$itinerary->id}/edit")
            ->assertSessionHasErrors([$error ?? $field]);
        $this->assertUnchanged($itinerary, [$place->id]);
    }

    public static function invalidValues(): array
    {
        return [
            'missing title' => ['title', null],
            'short title' => ['title', 'ab'],
            'long title' => ['title', str_repeat('a', 256)],
            'non-string title' => ['title', ['abc']],
            'missing city' => ['city_id', null],
            'unknown city' => ['city_id', 9999],
            'non-numeric city' => ['city_id', 'abc'],
            'array city' => ['city_id', [1]],
            'non-array places' => ['place_ids', 'abc'],
            'unknown place' => ['place_ids', [9999], 'place_ids.0'],
            'non-numeric place' => ['place_ids', ['abc'], 'place_ids.0'],
            'nested place' => ['place_ids', [[1]], 'place_ids.0'],
            'empty place' => ['place_ids', [null], 'place_ids.0'],
        ];
    }

    public function test_places_from_another_city_are_rejected()
    {
        $city = City::factory()->create();
        $place = Place::factory()->for($city)->create();
        $foreign = Place::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post('/user/itineraries', $this->validData([
                'city_id' => $city->id,
                'place_ids' => [$place->id, $foreign->id],
            ]))
            ->assertSessionHasErrors(['place_ids.1' => 'All selected places must belong to the selected city.'])
            ->assertSessionDoesntHaveErrors(['place_ids.0']);

        $this->assertDatabaseCount('itineraries', 0);
        $this->assertDatabaseCount('itinerary_place', 0);
    }

    public function test_duplicate_places_are_rejected()
    {
        $place = Place::factory()->create();
        $data = $this->validData([
            'city_id' => $place->city_id,
            'place_ids' => [$place->id, (string) $place->id],
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/user/itineraries', $data)
            ->assertSessionHasErrors(['place_ids.0' => 'Each place can only be selected once.']);

        $this->assertDatabaseCount('itineraries', 0);

        $itinerary = Itinerary::factory()->for($place->city)->for($user)->create();
        $itinerary->places()->attach($place);

        $this->put("/user/itineraries/{$itinerary->id}", $data)
            ->assertSessionHasErrors(['place_ids.0']);

        $this->assertUnchanged($itinerary, [$place->id]);
    }

    public function test_edit_page_receives_the_itinerary_with_its_stops_in_order()
    {
        $city = City::factory()->create(['name' => 'Barcelona']);
        [$a, $b, $c] = $this->placesNamed($city, ['Alpha', 'Bravo', 'Charlie']);
        $itinerary = Itinerary::factory()->for($city)->create(['title' => 'Gaudí']);
        // Saved in an order that is neither alphabetical nor by id.
        $itinerary->syncStops([$c->id, $a->id]);

        $this->actingAs($itinerary->user)
            ->get("/user/itineraries/{$itinerary->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/itineraries/edit')
                ->where('itinerary', [
                    'id' => $itinerary->id,
                    'title' => 'Gaudí',
                    'city_id' => $city->id,
                    'place_ids' => [$c->id, $a->id],
                ])
                ->where('cities', [['id' => $city->id, 'name' => 'Barcelona']])
                ->where('places', fn ($places) => $places->pluck('id')->all() === [$a->id, $b->id, $c->id]));
    }

    public function test_edit_page_does_not_preselect_a_place_that_moved_to_another_city()
    {
        $itinerary = Itinerary::factory()->create();
        $place = Place::factory()->for($itinerary->city)->create();
        $moved = Place::factory()->for($itinerary->city)->create();
        $itinerary->places()->attach([$place->id, $moved->id]);
        $moved->update(['city_id' => City::factory()->create()->id]);

        $this->actingAs($itinerary->user)
            ->get("/user/itineraries/{$itinerary->id}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->where('itinerary.place_ids', [$place->id]));
    }

    public function test_updating_changes_the_fields_and_preserves_the_author()
    {
        $other = Itinerary::factory()->create();
        $itinerary = Itinerary::factory()->create();
        $itinerary->places()->attach(Place::factory()->for($itinerary->city)->create());
        $newPlace = Place::factory()->create();

        $this->actingAs($itinerary->user)
            ->put("/user/itineraries/{$itinerary->id}", [
                'title' => 'A new title',
                'city_id' => $newPlace->city_id,
                'place_ids' => [$newPlace->id],
                'user_id' => User::factory()->create()->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.itineraries.index'));

        $this->assertDatabaseHas('itineraries', [
            'id' => $itinerary->id,
            'title' => 'A new title',
            'city_id' => $newPlace->city_id,
            'user_id' => $itinerary->user_id,
        ]);
        $this->assertStops([$newPlace->id], $itinerary);
        $this->assertEquals($other->getAttributes(), $other->fresh()->getAttributes());
    }

    public function test_updating_adds_removes_and_reorders_stops_without_touching_other_itineraries()
    {
        $city = City::factory()->create();
        [$a, $b, $c, $d] = $this->placesNamed($city, ['Alpha', 'Bravo', 'Charlie', 'Delta']);
        $itinerary = Itinerary::factory()->for($city)->create();
        $itinerary->syncStops([$a->id, $b->id, $c->id]);
        $other = Itinerary::factory()->for($city)->create();
        $other->syncStops([$b->id]);

        $this->actingAs($itinerary->user)
            ->put("/user/itineraries/{$itinerary->id}", $this->validData([
                'city_id' => $city->id,
                'place_ids' => [$c->id, $d->id, $a->id],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertStops([$c->id, $d->id, $a->id], $itinerary);
        $this->assertStops([$b->id], $other);
    }

    public function test_changing_the_city_replaces_the_stops_with_places_from_the_new_city()
    {
        $itinerary = Itinerary::factory()->create();
        $old = Place::factory()->for($itinerary->city)->create();
        $itinerary->syncStops([$old->id]);
        $city = City::factory()->create();
        [$a, $b] = $this->placesNamed($city, ['Alpha', 'Bravo']);

        $this->actingAs($itinerary->user)
            ->put("/user/itineraries/{$itinerary->id}", $this->validData([
                'city_id' => $city->id,
                'place_ids' => [$b->id, $a->id],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertStops([$b->id, $a->id], $itinerary);

        // A stop from the previous city cannot stay selected.
        $this->put("/user/itineraries/{$itinerary->id}", $this->validData([
            'city_id' => $city->id,
            'place_ids' => [$a->id, $old->id],
        ]))->assertSessionHasErrors(['place_ids.1']);

        $this->assertStops([$b->id, $a->id], $itinerary);
    }

    public function test_deleting_an_itinerary_only_removes_the_itinerary_and_its_stops()
    {
        $itinerary = Itinerary::factory()->create();
        $place = Place::factory()->for($itinerary->city)->create();
        $itinerary->places()->attach($place);
        $other = Itinerary::factory()->for($itinerary->city)->create();
        $other->places()->attach($place);

        $this->actingAs($itinerary->user)
            ->delete("/user/itineraries/{$itinerary->id}")
            ->assertRedirect(route('userzone.itineraries.index'));

        $this->assertModelMissing($itinerary);
        $this->assertDatabaseMissing('itinerary_place', ['itinerary_id' => $itinerary->id]);
        $this->assertModelExists($itinerary->city);
        $this->assertModelExists($itinerary->user);
        $this->assertModelExists($place);
        $this->assertUnchanged($other, [$place->id]);
    }

    public function test_other_users_cannot_open_update_or_delete_an_itinerary()
    {
        $itinerary = Itinerary::factory()->create();
        $place = Place::factory()->for($itinerary->city)->create();
        $itinerary->places()->attach($place);
        $newPlace = Place::factory()->create();
        $links = DB::table('itinerary_place')->get();
        $intruder = User::factory()->create();
        $takeover = [
            'title' => 'Taken over',
            'city_id' => $newPlace->city_id,
            'place_ids' => [$newPlace->id],
            'user_id' => $intruder->id,
        ];

        $this->actingAs($intruder);

        $this->get("/user/itineraries/{$itinerary->id}")->assertForbidden();
        $this->get("/user/itineraries/{$itinerary->id}/edit")->assertForbidden();
        // Ownership is checked before validation, so the response reveals no validation errors.
        $this->put("/user/itineraries/{$itinerary->id}", [])->assertForbidden()->assertSessionHasNoErrors();
        $this->put("/user/itineraries/{$itinerary->id}", $takeover)->assertForbidden();
        $this->patch("/user/itineraries/{$itinerary->id}", $takeover)->assertForbidden();
        $this->delete("/user/itineraries/{$itinerary->id}")->assertForbidden();

        $this->assertUnchanged($itinerary, [$place->id]);
        $this->assertEquals($links, DB::table('itinerary_place')->get());
    }

    public function test_administrators_can_manage_another_users_itinerary_without_changing_its_author()
    {
        $city = City::factory()->create();
        [$a, $b] = $this->placesNamed($city, ['Alpha', 'Bravo']);
        $itinerary = Itinerary::factory()->for($city)->create();
        $itinerary->syncStops([$a->id, $b->id]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->get("/user/itineraries/{$itinerary->id}")->assertRedirect(route('itineraries.show', $itinerary));
        $this->get("/user/itineraries/{$itinerary->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('itinerary.place_ids', [$a->id, $b->id]));

        $this->put("/user/itineraries/{$itinerary->id}", [
            'title' => 'Reviewed title',
            'city_id' => $city->id,
            'place_ids' => [$b->id, $a->id],
            'user_id' => $admin->id,
        ])->assertSessionHasNoErrors()->assertRedirect(route('userzone.itineraries.index'));

        $this->assertDatabaseHas('itineraries', ['id' => $itinerary->id, 'title' => 'Reviewed title', 'user_id' => $itinerary->user_id]);
        $this->assertStops([$b->id, $a->id], $itinerary);

        $this->delete("/user/itineraries/{$itinerary->id}")->assertRedirect(route('userzone.itineraries.index'));

        $this->assertModelMissing($itinerary);
        $this->assertModelExists($itinerary->user);
    }

    public function test_the_private_show_action_sends_the_owner_to_the_public_page()
    {
        $itinerary = Itinerary::factory()->create();

        $this->actingAs($itinerary->user)
            ->get(route('userzone.itineraries.show', $itinerary))
            ->assertRedirect(route('itineraries.show', $itinerary));
    }

    public function test_a_missing_itinerary_returns_not_found()
    {
        $this->actingAs(User::factory()->create());

        $this->get('/user/itineraries/9999/edit')->assertNotFound();
        $this->put('/user/itineraries/9999', $this->validData())->assertNotFound();
        $this->delete('/user/itineraries/9999')->assertNotFound();

        $this->assertDatabaseCount('itineraries', 0);
    }

    public function test_the_position_migration_keeps_existing_links_and_numbers_them_alphabetically()
    {
        $migration = '2026_11_26_000001_add_position_to_itinerary_place_table';
        $database = tempnam(sys_get_temp_dir(), 'itinerarium');
        config(['database.connections.legacy' => [...config('database.connections.sqlite'), 'url' => null, 'database' => $database]]);
        $this->beforeApplicationDestroyed(function () use ($database) {
            DB::purge('legacy');
            @unlink($database);
        });
        $migrations = glob(database_path('migrations/*.php'));
        $migrate = fn (callable $filter) => $this->artisan('migrate', [
            '--database' => 'legacy',
            '--path' => array_values(array_filter($migrations, fn (string $path) => $filter(basename($path, '.php')))),
            '--realpath' => true,
        ])->assertSuccessful();

        $migrate(fn (string $name) => $name < $migration);
        $db = DB::connection('legacy');
        $this->assertFalse($db->getSchemaBuilder()->hasColumn('itinerary_place', 'position'));

        $now = now();
        $userId = $db->table('users')->insertGetId(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'x', 'created_at' => $now, 'updated_at' => $now]);
        $cityId = $db->table('cities')->insertGetId(['name' => 'Barcelona', 'user_id' => $userId, 'created_at' => $now, 'updated_at' => $now]);
        $place = fn (string $name) => $db->table('places')->insertGetId([
            'name' => $name, 'description' => '', 'latitude' => 41.4, 'longitude' => 2.17, 'city_id' => $cityId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $itinerary = fn (string $title) => $db->table('itineraries')->insertGetId(['title' => $title, 'user_id' => $userId, 'city_id' => $cityId, 'created_at' => $now, 'updated_at' => $now]);
        $sagrada = $place('Sagrada Família');
        $parkFirst = $place('Park Güell');
        $casa = $place('Casa Batlló');
        $parkSecond = $place('Park Güell');
        $weekend = $itinerary('Weekend');
        $onFoot = $itinerary('On foot');
        $empty = $itinerary('Empty');
        $links = [
            [$weekend, $sagrada], [$weekend, $parkSecond], [$weekend, $casa], [$weekend, $parkFirst],
            [$onFoot, $sagrada], [$onFoot, $casa],
        ];
        $db->table('itinerary_place')->insert(array_map(fn ($link) => ['itinerary_id' => $link[0], 'place_id' => $link[1]], $links));

        $migrate(fn (string $name) => $name === $migration);

        $rows = $db->table('itinerary_place')->orderBy('itinerary_id')->orderBy('position')->get()
            ->map(fn ($row) => [$row->itinerary_id, $row->place_id, $row->position])->all();
        $this->assertSame([
            [$weekend, $casa, 1], [$weekend, $parkFirst, 2], [$weekend, $parkSecond, 3], [$weekend, $sagrada, 4],
            [$onFoot, $casa, 1], [$onFoot, $sagrada, 2],
        ], $rows);
        $this->assertSame(3, $db->table('itineraries')->count());
        $this->assertSame(4, $db->table('places')->count());
        $this->assertSame(0, $db->table('itinerary_place')->where('itinerary_id', $empty)->count());
    }

    private function placesNamed(City $city, array $names): array
    {
        return array_map(fn (string $name) => Place::factory()->for($city)->create(['name' => $name]), $names);
    }

    /** The stops in this order, numbered from 1. */
    private function assertStops(array $expected, Itinerary $itinerary): void
    {
        $this->assertSame($expected, $itinerary->places()->pluck('places.id')->all());
        $this->assertSame(
            $expected === [] ? [] : range(1, count($expected)),
            DB::table('itinerary_place')->where('itinerary_id', $itinerary->id)->orderBy('position')->pluck('position')->map(fn ($position) => (int) $position)->all(),
        );
    }

    private function assertUnchanged(Itinerary $itinerary, array $placeIds): void
    {
        $this->assertDatabaseHas('itineraries', $itinerary->only(['id', 'title', 'city_id', 'user_id']));
        $this->assertEqualsCanonicalizing($placeIds, $itinerary->places()->pluck('places.id')->all());
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'A weekend away',
            'city_id' => City::factory()->create()->id,
        ], $overrides);
    }
}
