<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserzonePlaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/user/places')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_see_all_places_with_their_city()
    {
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $bangkok = City::factory()->create(['name' => 'Bangkok']);
        Place::factory()->for($bangkok)->create(['name' => 'Wat Pho']);
        Place::factory()->for($barcelona)->create(['name' => 'Casa Batlló']);

        $this->actingAs(User::factory()->create())
            ->get('/user/places')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/places/index')
                ->has('places', 2)
                ->where('places.0.name', 'Casa Batlló')
                ->where('places.0.city.name', 'Barcelona')
                ->where('places.1.name', 'Wat Pho')
                ->where('places.1.city.name', 'Bangkok'));
    }

    public function test_overview_renders_when_there_are_no_places()
    {
        $this->actingAs(User::factory()->create())
            ->get('/user/places')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/places/index')
                ->has('places', 0));
    }

    public function test_guests_cannot_open_the_create_page()
    {
        $this->get('/user/places/create')->assertRedirect('/login');
    }

    public function test_guests_cannot_store_a_place()
    {
        $this->post('/user/places', $this->validData())->assertRedirect('/login');

        $this->assertDatabaseCount('places', 0);
    }

    public function test_create_page_receives_the_available_cities()
    {
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $bangkok = City::factory()->create(['name' => 'Bangkok']);

        $this->actingAs(User::factory()->create())
            ->get('/user/places/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/places/create')
                ->has('cities', 2)
                ->where('cities.0', ['id' => $bangkok->id, 'name' => 'Bangkok'])
                ->where('cities.1', ['id' => $barcelona->id, 'name' => 'Barcelona']));
    }

    public function test_authenticated_users_can_create_a_place()
    {
        $data = $this->validData();

        $this->actingAs(User::factory()->create())
            ->post('/user/places', $data)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));

        $this->assertDatabaseCount('places', 1);
        $this->assertDatabaseHas('places', $data);

        $this->get('/user/places')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('places', 1)
                ->where('places.0.name', 'Sagrada Família'));

        $this->get('/places')->assertOk();
        $this->get('/places/'.Place::sole()->id)->assertOk();
    }

    #[DataProvider('boundaryCoordinates')]
    public function test_boundary_coordinates_are_accepted(int $latitude, int $longitude)
    {
        $data = $this->validData(['latitude' => $latitude, 'longitude' => $longitude]);

        $this->actingAs(User::factory()->create())
            ->post('/user/places', $data)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('places', $data);
    }

    public static function boundaryCoordinates(): array
    {
        return [
            'minimum' => [-90, -180],
            'zero' => [0, 0],
            'maximum' => [90, 180],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected(string $field, mixed $value)
    {
        $this->actingAs(User::factory()->create())
            ->from('/user/places/create')
            ->post('/user/places', $this->validData([$field => $value]))
            ->assertRedirect('/user/places/create')
            ->assertSessionHasErrors([$field]);

        $this->assertDatabaseCount('places', 0);
    }

    public static function invalidValues(): array
    {
        return [
            'missing name' => ['name', null],
            'short name' => ['name', 'ab'],
            'long name' => ['name', str_repeat('a', 256)],
            'non-string name' => ['name', ['abc']],
            'missing description' => ['description', null],
            'non-string description' => ['description', ['abc']],
            'missing city' => ['city_id', null],
            'unknown city' => ['city_id', 9999],
            'non-numeric city' => ['city_id', 'abc'],
            'missing latitude' => ['latitude', null],
            'non-numeric latitude' => ['latitude', 'abc'],
            'latitude too low' => ['latitude', -90.1],
            'latitude too high' => ['latitude', 90.1],
            'latitude too precise' => ['latitude', '41.4036299'],
            'missing longitude' => ['longitude', null],
            'non-numeric longitude' => ['longitude', 'abc'],
            'longitude too low' => ['longitude', -180.1],
            'longitude too high' => ['longitude', 180.1],
            'longitude too precise' => ['longitude', '2.1743558'],
        ];
    }

    public function test_guests_cannot_open_the_edit_page()
    {
        $place = Place::factory()->create();

        $this->get("/user/places/{$place->id}/edit")->assertRedirect('/login');
    }

    public function test_guests_cannot_update_a_place()
    {
        $place = Place::factory()->create();

        $this->put("/user/places/{$place->id}", $this->validData())->assertRedirect('/login');

        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
    }

    public function test_edit_page_receives_the_place_and_the_available_cities()
    {
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $bangkok = City::factory()->create(['name' => 'Bangkok']);
        Place::factory()->for($bangkok)->create();
        $place = Place::factory()->for($barcelona)->create([
            'name' => 'Casa Batlló',
            'description' => 'A house by Gaudí.',
            'latitude' => 41.39164,
            'longitude' => 2.16489,
        ]);

        $this->actingAs(User::factory()->create())
            ->get("/user/places/{$place->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/places/edit')
                ->where('place', [
                    'id' => $place->id,
                    'name' => 'Casa Batlló',
                    'description' => 'A house by Gaudí.',
                    'city_id' => $barcelona->id,
                    'latitude' => 41.39164,
                    'longitude' => 2.16489,
                ])
                ->has('cities', 2)
                ->where('cities.0', ['id' => $bangkok->id, 'name' => 'Bangkok'])
                ->where('cities.1', ['id' => $barcelona->id, 'name' => 'Barcelona']));
    }

    public function test_authenticated_users_can_update_a_place()
    {
        $other = Place::factory()->create();
        $place = Place::factory()->create();
        $data = $this->validData();

        $this->actingAs(User::factory()->create())
            ->put("/user/places/{$place->id}", $data)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.places.index'));

        $this->assertDatabaseCount('places', 2);
        $this->assertDatabaseHas('places', ['id' => $place->id, ...$data]);
        $this->assertEquals($other->getAttributes(), $other->fresh()->getAttributes());

        $this->get('/places')->assertOk();
        $this->get("/places/{$place->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('place.name', 'Sagrada Família'));
    }

    #[DataProvider('boundaryCoordinates')]
    public function test_boundary_coordinates_are_accepted_when_updating(int $latitude, int $longitude)
    {
        $place = Place::factory()->create();
        $data = $this->validData(['latitude' => $latitude, 'longitude' => $longitude]);

        $this->actingAs(User::factory()->create())
            ->put("/user/places/{$place->id}", $data)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('places', ['id' => $place->id, ...$data]);
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected_when_updating(string $field, mixed $value)
    {
        $place = Place::factory()->create();

        $this->actingAs(User::factory()->create())
            ->from("/user/places/{$place->id}/edit")
            ->put("/user/places/{$place->id}", $this->validData([$field => $value]))
            ->assertRedirect("/user/places/{$place->id}/edit")
            ->assertSessionHasErrors([$field]);

        $this->assertDatabaseCount('places', 1);
        $this->assertEquals($place->getAttributes(), $place->fresh()->getAttributes());
    }

    public function test_editing_or_updating_a_nonexistent_place_returns_not_found()
    {
        $this->actingAs(User::factory()->create());

        $this->get('/user/places/9999/edit')->assertNotFound();
        $this->put('/user/places/9999', $this->validData())->assertNotFound();

        $this->assertDatabaseCount('places', 0);
    }

    public function test_guests_cannot_delete_a_place()
    {
        $place = Place::factory()->create();

        $this->delete("/user/places/{$place->id}")->assertRedirect('/login');

        $this->assertModelExists($place);
    }

    public function test_authenticated_users_can_delete_a_place()
    {
        $other = Place::factory()->create();
        $place = Place::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete("/user/places/{$place->id}")
            ->assertRedirect(route('userzone.places.index'));

        $this->assertModelMissing($place);
        $this->assertModelExists($other);

        $this->get('/places')->assertOk();
        $this->get("/places/{$place->id}")->assertNotFound();
        $this->get("/places/{$other->id}")->assertOk();
    }

    public function test_deleting_a_place_only_removes_its_own_itinerary_links()
    {
        $city = City::factory()->create();
        $place = Place::factory()->for($city)->create();
        $other = Place::factory()->for($city)->create();
        $itineraries = Itinerary::factory()->count(2)->create();
        $itineraries->each(fn (Itinerary $itinerary) => $itinerary->places()->attach([$place->id, $other->id]));

        $cityAttributes = $city->fresh()->getAttributes();
        $itineraryAttributes = $itineraries->map(fn (Itinerary $itinerary) => $itinerary->fresh()->getAttributes());

        $this->actingAs(User::factory()->create())
            ->delete("/user/places/{$place->id}")
            ->assertRedirect(route('userzone.places.index'));

        $this->assertModelMissing($place);
        $this->assertDatabaseMissing('itinerary_place', ['place_id' => $place->id]);
        $this->assertDatabaseCount('itinerary_place', 2);
        $this->assertEquals($cityAttributes, $city->fresh()->getAttributes());
        $this->assertEquals(
            $itineraryAttributes,
            $itineraries->map(fn (Itinerary $itinerary) => $itinerary->fresh()->getAttributes()),
        );

        foreach ($itineraries as $itinerary) {
            $this->assertDatabaseHas('itinerary_place', [
                'itinerary_id' => $itinerary->id,
                'place_id' => $other->id,
            ]);
        }
    }

    public function test_deleting_a_nonexistent_place_returns_not_found()
    {
        $place = Place::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete('/user/places/9999')
            ->assertNotFound();

        $this->assertModelExists($place);
    }

    #[DataProvider('resourceRoutes')]
    public function test_resource_routes_have_the_expected_uris_and_methods(string $action, string $uri, array $methods)
    {
        $route = Route::getRoutes()->getByName("userzone.places.{$action}");

        $this->assertNotNull($route);
        $this->assertSame($uri, $route->uri());
        $this->assertSame($methods, $route->methods());
        $this->assertContains('auth', $route->gatherMiddleware());
    }

    public static function resourceRoutes(): array
    {
        return [
            'index' => ['index', 'user/places', ['GET', 'HEAD']],
            'create' => ['create', 'user/places/create', ['GET', 'HEAD']],
            'store' => ['store', 'user/places', ['POST']],
            'edit' => ['edit', 'user/places/{place}/edit', ['GET', 'HEAD']],
            'update' => ['update', 'user/places/{place}', ['PUT', 'PATCH']],
            'destroy' => ['destroy', 'user/places/{place}', ['DELETE']],
        ];
    }

    public function test_there_is_no_private_show_route()
    {
        $this->assertFalse(Route::has('userzone.places.show'));
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sagrada Família',
            'description' => 'A basilica by Gaudí.',
            'city_id' => City::factory()->create()->id,
            'latitude' => 41.40363,
            'longitude' => 2.174356,
        ], $overrides);
    }
}
