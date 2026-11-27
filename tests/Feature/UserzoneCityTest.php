<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * City management in the Userzone, administrators only.
 */
class UserzoneCityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_city_management()
    {
        $city = City::factory()->create();

        $this->get('/user/cities')->assertRedirect('/login');
        $this->get('/user/cities/create')->assertRedirect('/login');
        $this->post('/user/cities', ['name' => 'Barcelona'])->assertRedirect('/login');
        $this->get("/user/cities/{$city->id}/edit")->assertRedirect('/login');
        $this->put("/user/cities/{$city->id}", ['name' => 'Barcelona'])->assertRedirect('/login');
        $this->delete("/user/cities/{$city->id}")->assertRedirect('/login');

        $this->assertDatabaseCount('cities', 1);
        $this->assertEquals($city->getAttributes(), $city->fresh()->getAttributes());
    }

    public function test_regular_users_cannot_open_the_city_management_pages()
    {
        $city = City::factory()->create();

        $this->actingAs(User::factory()->create());

        $this->get('/user/cities')->assertForbidden();
        $this->get('/user/cities/create')->assertForbidden();
        $this->get("/user/cities/{$city->id}/edit")->assertForbidden();
    }

    public function test_regular_users_cannot_create_update_or_delete_cities_even_their_own()
    {
        $user = User::factory()->create();
        $own = City::factory()->for($user)->create();
        $occupied = City::factory()->create();
        [$place, $itinerary] = $this->fill($occupied);

        $this->actingAs($user);

        // Rejected before validation, so the response reveals no validation errors.
        $this->post('/user/cities', [])->assertForbidden()->assertSessionHasNoErrors();
        $this->put("/user/cities/{$own->id}", [])->assertForbidden()->assertSessionHasNoErrors();

        $this->post('/user/cities', ['name' => 'Barcelona'])->assertForbidden();
        foreach ([$own, $occupied] as $city) {
            $this->put("/user/cities/{$city->id}", ['name' => 'New name'])->assertForbidden();
            $this->patch("/user/cities/{$city->id}", ['name' => 'New name'])->assertForbidden();
            $this->delete("/user/cities/{$city->id}")->assertForbidden()->assertSessionHasNoErrors();

            $this->assertEquals($city->getAttributes(), $city->fresh()->getAttributes());
        }

        $this->assertDatabaseCount('cities', 2);
        $this->assertIntact($place, $itinerary);
    }

    public function test_administrators_can_see_all_cities_with_counts()
    {
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $bangkok = City::factory()->create(['name' => 'Bangkok']);
        $ghent = City::factory()->create(['name' => 'Ghent']);
        Place::factory()->count(3)->for($barcelona)->create();
        Itinerary::factory()->count(2)->for($barcelona)->create();
        Itinerary::factory()->for($bangkok)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/user/cities')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/cities/index')
                ->where('cities', [
                    ['id' => $bangkok->id, 'name' => 'Bangkok', 'places_count' => 0, 'itineraries_count' => 1],
                    ['id' => $barcelona->id, 'name' => 'Barcelona', 'places_count' => 3, 'itineraries_count' => 2],
                    ['id' => $ghent->id, 'name' => 'Ghent', 'places_count' => 0, 'itineraries_count' => 0],
                ]));
    }

    public function test_the_create_and_edit_pages_render()
    {
        $city = City::factory()->create(['name' => 'Barcelona']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/user/cities/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('userzone/cities/create'));

        $this->get("/user/cities/{$city->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('userzone/cities/edit')
                ->where('city', ['id' => $city->id, 'name' => 'Barcelona']));
    }

    public function test_administrators_can_create_a_city_as_its_creator()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/user/cities', ['name' => 'Barcelona', 'user_id' => User::factory()->create()->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.cities.index'));

        $city = City::sole();
        $this->assertSame('Barcelona', $city->name);
        $this->assertSame($admin->id, $city->user_id);
    }

    #[DataProvider('validNames')]
    public function test_boundary_names_are_accepted(string $name)
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/user/cities', ['name' => $name])
            ->assertSessionHasNoErrors();

        $city = City::sole();
        $this->assertSame($name, $city->name);

        $city->update(['name' => 'Something else']);

        $this->put("/user/cities/{$city->id}", ['name' => $name])
            ->assertSessionHasNoErrors();

        $this->assertSame($name, $city->fresh()->name);
    }

    public static function validNames(): array
    {
        return [
            'shortest' => ['abc'],
            'longest' => [str_repeat('a', 255)],
            'shortest multibyte' => ['Ålé'],
        ];
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_names_are_rejected_when_creating_and_updating(array $data)
    {
        $city = City::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->from('/user/cities/create')
            ->post('/user/cities', $data)
            ->assertRedirect('/user/cities/create')
            ->assertSessionHasErrors(['name']);
        $this->assertDatabaseCount('cities', 1);

        $this->from("/user/cities/{$city->id}/edit")
            ->put("/user/cities/{$city->id}", $data)
            ->assertRedirect("/user/cities/{$city->id}/edit")
            ->assertSessionHasErrors(['name']);
        $this->assertEquals($city->getAttributes(), $city->fresh()->getAttributes());
    }

    public static function invalidNames(): array
    {
        return [
            'omitted' => [[]],
            'null' => [['name' => null]],
            'blank' => [['name' => '   ']],
            'too short' => [['name' => 'ab']],
            'too long' => [['name' => str_repeat('a', 256)]],
            'not a string' => [['name' => ['abc']]],
        ];
    }

    public function test_updating_changes_only_the_name()
    {
        $other = City::factory()->create();
        $city = City::factory()->create(['name' => 'Old name']);
        [$place, $itinerary] = $this->fill($city);
        $editor = User::factory()->admin()->create();

        $this->actingAs($editor)
            ->put("/user/cities/{$city->id}", [
                'name' => 'New name',
                'id' => 9999,
                'user_id' => $editor->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.cities.index'));

        $this->assertDatabaseCount('cities', 2);
        $this->assertDatabaseHas('cities', [
            'id' => $city->id,
            'name' => 'New name',
            'user_id' => $city->user_id,
        ]);
        $this->assertEquals($other->getAttributes(), $other->fresh()->getAttributes());
        $this->assertIntact($place, $itinerary);
    }

    public function test_an_empty_city_can_be_deleted()
    {
        $city = City::factory()->create();
        $other = City::factory()->create();
        [$place, $itinerary] = $this->fill($other);

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/user/cities/{$city->id}")
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('userzone.cities.index'));

        $this->assertModelMissing($city);
        $this->assertModelExists($city->user);
        $this->assertIntact($place, $itinerary);
    }

    public function test_a_city_with_places_or_itineraries_cannot_be_deleted()
    {
        $withPlace = City::factory()->create(['name' => 'Barcelona']);
        $place = Place::factory()->for($withPlace)->create();
        $withItinerary = City::factory()->create(['name' => 'Barcelona']);
        $itinerary = Itinerary::factory()->for($withItinerary)->create();

        foreach ([$withPlace, $withItinerary] as $city) {
            $this->assertDeletionIsRejected($city);
            $this->assertEquals($city->getAttributes(), $city->fresh()->getAttributes());
        }

        $this->assertSame($withPlace->id, $place->fresh()->city_id);
        $this->assertSame($withItinerary->id, $itinerary->fresh()->city_id);
    }

    public function test_a_city_can_be_deleted_once_it_has_been_emptied()
    {
        $city = City::factory()->create(['name' => 'Barcelona']);
        [$place, $itinerary] = $this->fill($city);

        $this->assertDeletionIsRejected($city);
        $this->assertIntact($place, $itinerary);

        $itinerary->delete();
        $place->delete();

        $this->delete("/user/cities/{$city->id}")->assertSessionHasNoErrors();

        $this->assertModelMissing($city);
    }

    public function test_a_missing_city_returns_not_found()
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/user/cities/9999/edit')->assertNotFound();
        $this->put('/user/cities/9999', ['name' => 'Barcelona'])->assertNotFound();
        $this->delete('/user/cities/9999')->assertNotFound();

        $this->assertDatabaseCount('cities', 0);
    }

    private function fill(City $city): array
    {
        $place = Place::factory()->for($city)->create();
        $itinerary = Itinerary::factory()->for($city)->create();
        $itinerary->places()->attach($place);

        return [$place, $itinerary];
    }

    private function assertDeletionIsRejected(City $city): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->delete("/user/cities/{$city->id}")
            ->assertRedirect(route('userzone.cities.index'))
            ->assertSessionHasErrors(['city' => '"Barcelona" cannot be deleted because it still has places or itineraries.']);

        $this->assertModelExists($city);
    }

    private function assertIntact(Place $place, Itinerary $itinerary): void
    {
        $this->assertDatabaseHas('places', $place->only(['id', 'name', 'city_id']));
        $this->assertDatabaseHas('itineraries', $itinerary->only(['id', 'title', 'city_id', 'user_id']));
        $this->assertDatabaseHas('itinerary_place', ['place_id' => $place->id, 'itinerary_id' => $itinerary->id]);
    }
}
