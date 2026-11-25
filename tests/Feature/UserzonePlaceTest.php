<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
