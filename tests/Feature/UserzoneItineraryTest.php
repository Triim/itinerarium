<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserzoneItineraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_itinerary_management()
    {
        $itinerary = Itinerary::factory()->create();

        $this->get('/user/itineraries')->assertRedirect('/login');
        $this->get('/user/itineraries/create')->assertRedirect('/login');
        $this->post('/user/itineraries', $this->validData())->assertRedirect('/login');
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
                ->where('itineraries.1.city.name', 'Bangkok')
                ->where('itineraries.1.user.name', 'Ada'));

        $this->actingAs($grace)
            ->get('/user/itineraries')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('itineraries', 1)
                ->where('itineraries.0.title', 'Markets')
                ->where('itineraries.0.user.name', 'Grace'));
    }

    public function test_overview_is_empty_when_only_other_users_have_itineraries()
    {
        Itinerary::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get('/user/itineraries')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('itineraries', 0));
    }

    public function test_overview_renders_when_there_are_no_itineraries()
    {
        $this->actingAs(User::factory()->create())
            ->get('/user/itineraries')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/itineraries/index')
                ->has('itineraries', 0));
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

    public function test_authenticated_users_can_create_an_itinerary_with_places()
    {
        $user = User::factory()->create();
        $city = City::factory()->create();
        $places = Place::factory()->count(2)->for($city)->create();

        $this->actingAs($user)
            ->post('/user/itineraries', [
                'title' => 'Two days in town',
                'city_id' => $city->id,
                'place_ids' => $places->modelKeys(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.itineraries.index'));

        $itinerary = Itinerary::sole();

        $this->assertSame('Two days in town', $itinerary->title);
        $this->assertSame($city->id, $itinerary->city_id);
        $this->assertSame($user->id, $itinerary->user_id);
        $this->assertPlaces($places->modelKeys(), $itinerary);
    }

    public function test_the_author_cannot_be_submitted_when_creating()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->post('/user/itineraries', $this->validData(['user_id' => $other->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($user->id, Itinerary::sole()->user_id);
    }

    #[DataProvider('emptySelections')]
    public function test_an_itinerary_can_be_created_without_places(array $selection)
    {
        $this->actingAs(User::factory()->create())
            ->post('/user/itineraries', $this->validData($selection))
            ->assertSessionHasNoErrors();

        $this->assertPlaces([], Itinerary::sole());
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
    public function test_invalid_values_are_rejected(string $field, mixed $value, ?string $error = null)
    {
        $this->actingAs(User::factory()->create())
            ->from('/user/itineraries/create')
            ->post('/user/itineraries', $this->validData([$field => $value]))
            ->assertRedirect('/user/itineraries/create')
            ->assertSessionHasErrors([$error ?? $field]);

        $this->assertDatabaseCount('itineraries', 0);
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected_when_updating(string $field, mixed $value, ?string $error = null)
    {
        $itinerary = Itinerary::factory()->create();
        $place = Place::factory()->for($itinerary->city)->create();
        $itinerary->places()->attach($place);

        $this->actingAs($itinerary->user)
            ->from("/user/itineraries/{$itinerary->id}/edit")
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

    public function test_edit_page_receives_the_itinerary_with_its_selected_places()
    {
        $city = City::factory()->create(['name' => 'Barcelona']);
        $selected = Place::factory()->for($city)->create(['name' => 'Park Güell']);
        $unselected = Place::factory()->for($city)->create(['name' => 'Casa Batlló']);
        $itinerary = Itinerary::factory()->for($city)->create(['title' => 'Gaudí']);
        $itinerary->places()->attach($selected);

        $this->actingAs($itinerary->user)
            ->get("/user/itineraries/{$itinerary->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/itineraries/edit')
                ->where('itinerary', [
                    'id' => $itinerary->id,
                    'title' => 'Gaudí',
                    'city_id' => $city->id,
                    'place_ids' => [$selected->id],
                ])
                ->where('cities', [['id' => $city->id, 'name' => 'Barcelona']])
                ->has('places', 2)
                ->where('places.0.id', $unselected->id)
                ->where('places.1.id', $selected->id));
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
        $oldPlace = Place::factory()->for($itinerary->city)->create();
        $itinerary->places()->attach($oldPlace);
        $newPlace = Place::factory()->create();
        $newAuthor = User::factory()->create();

        $this->actingAs($itinerary->user)
            ->put("/user/itineraries/{$itinerary->id}", [
                'title' => 'A new title',
                'city_id' => $newPlace->city_id,
                'place_ids' => [$newPlace->id],
                'user_id' => $newAuthor->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.itineraries.index'));

        $this->assertDatabaseCount('itineraries', 2);
        $this->assertDatabaseHas('itineraries', [
            'id' => $itinerary->id,
            'title' => 'A new title',
            'city_id' => $newPlace->city_id,
            'user_id' => $itinerary->user_id,
        ]);
        $this->assertPlaces([$newPlace->id], $itinerary);
        $this->assertEquals($other->getAttributes(), $other->fresh()->getAttributes());
    }

    public function test_updating_adds_keeps_and_removes_places()
    {
        $city = City::factory()->create();
        [$kept, $removed, $added] = Place::factory()->count(3)->for($city)->create();
        $itinerary = Itinerary::factory()->for($city)->create();
        $itinerary->places()->attach([$kept->id, $removed->id]);
        $other = Itinerary::factory()->for($city)->create();
        $other->places()->attach($removed);

        $this->actingAs($itinerary->user)
            ->put("/user/itineraries/{$itinerary->id}", $this->validData([
                'city_id' => $city->id,
                'place_ids' => [$kept->id, $added->id],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertPlaces([$kept->id, $added->id], $itinerary);
        $this->assertPlaces([$removed->id], $other);
    }

    public function test_old_places_cannot_stay_selected_when_the_city_changes()
    {
        $itinerary = Itinerary::factory()->create();
        $place = Place::factory()->for($itinerary->city)->create();
        $itinerary->places()->attach($place);

        $this->actingAs($itinerary->user)
            ->put("/user/itineraries/{$itinerary->id}", $this->validData(['place_ids' => [$place->id]]))
            ->assertSessionHasErrors(['place_ids.0']);

        $this->assertUnchanged($itinerary, [$place->id]);
    }

    #[DataProvider('emptySelections')]
    public function test_updating_without_places_clears_the_selection(array $selection)
    {
        $itinerary = Itinerary::factory()->create();
        $itinerary->places()->attach(Place::factory()->for($itinerary->city)->create());

        $this->actingAs($itinerary->user)
            ->put("/user/itineraries/{$itinerary->id}", $this->validData([
                'city_id' => $itinerary->city_id,
                ...$selection,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertPlaces([], $itinerary);
    }

    public static function emptySelections(): array
    {
        return [
            'omitted' => [[]],
            'null' => [['place_ids' => null]],
            'empty array' => [['place_ids' => []]],
        ];
    }

    public function test_deleting_an_itinerary_only_removes_the_itinerary_and_its_place_links()
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
        $this->assertModelExists($other);
        $this->assertPlaces([$place->id], $other);
    }

    public function test_non_owners_cannot_open_the_edit_page()
    {
        $itinerary = Itinerary::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get("/user/itineraries/{$itinerary->id}/edit")
            ->assertForbidden();
    }

    #[DataProvider('updateMethods')]
    public function test_non_owners_cannot_update_an_itinerary(string $method)
    {
        $itinerary = Itinerary::factory()->create();
        $place = Place::factory()->for($itinerary->city)->create();
        $itinerary->places()->attach($place);
        $newPlace = Place::factory()->create();
        $pivotRows = DB::table('itinerary_place')->get();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->call($method, "/user/itineraries/{$itinerary->id}", [
                'title' => 'Taken over',
                'city_id' => $newPlace->city_id,
                'place_ids' => [$newPlace->id],
                'user_id' => $intruder->id,
            ])
            ->assertForbidden();

        $this->assertUnchanged($itinerary, [$place->id]);
        $this->assertEquals($pivotRows, DB::table('itinerary_place')->get());
    }

    public static function updateMethods(): array
    {
        return [
            'put' => ['PUT'],
            'patch' => ['PATCH'],
        ];
    }

    public function test_ownership_is_checked_before_validation()
    {
        $itinerary = Itinerary::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put("/user/itineraries/{$itinerary->id}", [])
            ->assertForbidden()
            ->assertSessionHasNoErrors();

        $this->assertUnchanged($itinerary, []);
    }

    public function test_non_owners_cannot_delete_an_itinerary()
    {
        $itinerary = Itinerary::factory()->create();
        $place = Place::factory()->for($itinerary->city)->create();
        $itinerary->places()->attach($place);
        $pivotRows = DB::table('itinerary_place')->get();

        $this->actingAs(User::factory()->create())
            ->delete("/user/itineraries/{$itinerary->id}")
            ->assertForbidden();

        $this->assertUnchanged($itinerary, [$place->id]);
        $this->assertEquals($pivotRows, DB::table('itinerary_place')->get());
    }

    public function test_a_missing_itinerary_returns_not_found()
    {
        $this->actingAs(User::factory()->create());

        $this->get('/user/itineraries/9999/edit')->assertNotFound();
        $this->put('/user/itineraries/9999', $this->validData())->assertNotFound();
        $this->delete('/user/itineraries/9999')->assertNotFound();

        $this->assertDatabaseCount('itineraries', 0);
    }

    public function test_public_pages_show_managed_itineraries()
    {
        $place = Place::factory()->create(['name' => 'Park Güell']);

        $this->actingAs(User::factory()->create())
            ->post('/user/itineraries', $this->validData([
                'title' => 'Gaudí',
                'city_id' => $place->city_id,
                'place_ids' => [$place->id],
            ]));

        $itinerary = Itinerary::sole();
        $this->post('/logout');
        $this->assertGuest();

        $this->get('/itineraries')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('itineraries/index')
                ->where('itineraries.0.title', 'Gaudí'));
        $this->get("/itineraries/{$itinerary->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('itineraries/show')
                ->where('itinerary.title', 'Gaudí')
                ->where('itinerary.places.0.name', 'Park Güell'));
    }

    public function test_public_pages_show_itineraries_from_different_users()
    {
        $ada = User::factory()->create(['name' => 'Ada']);
        $grace = User::factory()->create(['name' => 'Grace']);
        $itineraries = collect([
            Itinerary::factory()->for($ada)->create(['title' => 'Gaudí']),
            Itinerary::factory()->for($grace)->create(['title' => 'Temples']),
        ]);

        $assertVisible = function () use ($itineraries) {
            $this->get('/itineraries')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('itineraries/index')
                    ->has('itineraries', 2)
                    ->where('itineraries', fn ($shown) => $shown->pluck('title')->sort()->values()->all() === ['Gaudí', 'Temples']));

            foreach ($itineraries as $itinerary) {
                $this->get("/itineraries/{$itinerary->id}")
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page
                        ->component('itineraries/show')
                        ->where('itinerary.title', $itinerary->title)
                        ->where('itinerary.user.name', $itinerary->user->name));
            }
        };

        $assertVisible();

        $this->actingAs($ada);
        $assertVisible();
    }

    #[DataProvider('resourceRoutes')]
    public function test_resource_routes_have_the_expected_uris_and_methods(string $action, string $uri, array $methods)
    {
        $route = Route::getRoutes()->getByName("userzone.itineraries.{$action}");

        $this->assertNotNull($route);
        $this->assertSame($uri, $route->uri());
        $this->assertSame($methods, $route->methods());
        $this->assertContains('auth', $route->gatherMiddleware());
    }

    public static function resourceRoutes(): array
    {
        return [
            'index' => ['index', 'user/itineraries', ['GET', 'HEAD']],
            'create' => ['create', 'user/itineraries/create', ['GET', 'HEAD']],
            'store' => ['store', 'user/itineraries', ['POST']],
            'edit' => ['edit', 'user/itineraries/{itinerary}/edit', ['GET', 'HEAD']],
            'update' => ['update', 'user/itineraries/{itinerary}', ['PUT', 'PATCH']],
            'destroy' => ['destroy', 'user/itineraries/{itinerary}', ['DELETE']],
        ];
    }

    public function test_there_is_no_private_show_route()
    {
        $this->assertFalse(Route::has('userzone.itineraries.show'));
    }

    private function assertPlaces(array $expected, Itinerary $itinerary): void
    {
        $this->assertEqualsCanonicalizing($expected, $itinerary->places()->pluck('places.id')->all());
    }

    private function assertUnchanged(Itinerary $itinerary, array $placeIds): void
    {
        $this->assertDatabaseHas('itineraries', $itinerary->only(['id', 'title', 'city_id', 'user_id']));
        $this->assertPlaces($placeIds, $itinerary);
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'A weekend away',
            'city_id' => City::factory()->create()->id,
        ], $overrides);
    }
}
