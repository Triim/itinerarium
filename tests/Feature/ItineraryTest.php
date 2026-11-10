<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Layer;
use App\Models\Place;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItineraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_public_pages_render(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')->assertOk();
        $this->get('/cities')->assertOk()->assertInertia(fn (Assert $p) => $p->component('cities/index')->has('cities', 3));
        $this->get('/cities/rome')->assertOk()->assertInertia(fn (Assert $p) => $p->component('cities/show')->has('layers', 5));
        $this->get('/routes')->assertOk()->assertInertia(fn (Assert $p) => $p->component('itineraries/index')->has('itineraries.data', 5));
        $this->get('/collections')->assertOk()->assertInertia(fn (Assert $p) => $p->has('selections', 3));
        $this->get('/collections/cinema')->assertOk()->assertInertia(fn (Assert $p) => $p->has('selection.itineraries', 1));
    }

    public function test_routes_are_filtered_by_city_and_layer(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/routes?city=rome')->assertInertia(fn (Assert $p) => $p->has('itineraries.data', 2));
        $this->get('/routes?layers[]=filming')->assertInertia(fn (Assert $p) => $p->has('itineraries.data', 2));
        $this->get('/routes?city=tbilisi&layers[]=filming')->assertInertia(fn (Assert $p) => $p->has('itineraries.data', 0));
    }

    public function test_author_can_create_a_route_with_ordered_stops(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create();
        [$a, $b] = Place::factory()->count(2)->for($city)->create();

        $this->actingAs($user)->post('/routes', [
            'title' => 'Мой маршрут',
            'city_id' => $city->id,
            'is_published' => true,
            'place_ids' => [$b->id, $a->id],
        ])->assertRedirect();

        $itinerary = Itinerary::firstOrFail();
        $this->assertSame($user->id, $itinerary->user_id);
        $this->assertSame([$b->id, $a->id], $itinerary->places->pluck('id')->all());
    }

    public function test_stops_must_belong_to_the_chosen_city(): void
    {
        $user = User::factory()->create();
        $city = City::factory()->create();
        $foreign = Place::factory()->create();

        $this->actingAs($user)->post('/routes', [
            'title' => 'X', 'city_id' => $city->id, 'place_ids' => [$foreign->id],
        ])->assertSessionHasErrors('place_ids.0');
    }

    public function test_only_the_author_can_edit_update_or_delete(): void
    {
        $itinerary = Itinerary::factory()->create();
        $place = Place::factory()->for($itinerary->city)->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/routes/{$itinerary->id}/edit")->assertForbidden();
        $this->actingAs($stranger)->put("/routes/{$itinerary->id}", ['title' => 'Hack', 'city_id' => $itinerary->city_id, 'place_ids' => [$place->id]])->assertForbidden();
        $this->actingAs($stranger)->delete("/routes/{$itinerary->id}")->assertForbidden();

        $this->actingAs($itinerary->author)->put("/routes/{$itinerary->id}", [
            'title' => 'Обновлён', 'city_id' => $itinerary->city_id, 'place_ids' => [$place->id],
        ])->assertRedirect();
        $this->assertSame('Обновлён', $itinerary->fresh()->title);

        $this->actingAs($itinerary->author)->delete("/routes/{$itinerary->id}")->assertRedirect('/dashboard');
        $this->assertModelMissing($itinerary);
    }

    public function test_drafts_are_hidden_from_everyone_but_the_author(): void
    {
        $draft = Itinerary::factory()->draft()->create();

        $this->get("/routes/{$draft->id}")->assertForbidden();
        $this->actingAs(User::factory()->create())->get("/routes/{$draft->id}")->assertForbidden();
        $this->actingAs($draft->author)->get("/routes/{$draft->id}")->assertOk();
        $this->get('/routes')->assertInertia(fn (Assert $p) => $p->has('itineraries.data', 0));
    }

    public function test_guests_cannot_create_routes(): void
    {
        $this->get('/routes/create')->assertRedirect('/login');
        $this->post('/routes', [])->assertRedirect('/login');
    }

    public function test_author_email_is_not_exposed(): void
    {
        $itinerary = Itinerary::factory()->create();
        Layer::factory()->create();

        $this->get("/routes/{$itinerary->id}")
            ->assertInertia(fn (Assert $p) => $p->missing('itinerary.author.email')->where('itinerary.author.name', $itinerary->author->name));
    }
}
