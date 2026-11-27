<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\WelcomeController;
use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Checks for the course's explicit requirements: controller-backed routes,
 * one full resource controller, and factories used by DatabaseSeeder.
 */
class LecturerRequirementsTest extends TestCase
{
    use RefreshDatabase;

    /** Routes registered by Laravel itself (the health check and local file serving), not by routes/*.php. */
    private const FRAMEWORK_CLOSURE_URIS = ['up', 'storage/{path}'];

    public static function movedRoutes(): array
    {
        return [
            'welcome' => ['home', WelcomeController::class],
            'dashboard' => ['dashboard', DashboardController::class],
            'appearance' => ['appearance', AppearanceController::class.'@edit'],
        ];
    }

    #[DataProvider('movedRoutes')]
    public function test_former_closure_routes_use_controllers(string $name, string $action)
    {
        $this->assertSame($action, Route::getRoutes()->getByName($name)->getActionName());
    }

    public function test_no_application_route_is_a_closure()
    {
        foreach (Route::getRoutes() as $route) {
            if (in_array($route->uri(), self::FRAMEWORK_CLOSURE_URIS, true)) {
                continue;
            }

            $this->assertNotSame('Closure', $route->getActionName(), "/{$route->uri()} is a closure.");
        }
    }

    public function test_the_settings_redirect_uses_laravels_redirect_controller()
    {
        $route = collect(Route::getRoutes())->first(fn ($route) => $route->uri() === 'settings');

        $this->assertSame(RedirectController::class, ltrim($route->getActionName(), '\\'));
    }

    public function test_welcome_still_serves_the_cached_recent_itineraries()
    {
        config(['cache.default' => 'array']);
        Cache::forgetDriver('array');
        $itinerary = Itinerary::factory()->create();

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->where('itineraries', Itinerary::recentForWelcome()));

        $this->assertSame([$itinerary->id], array_column(Cache::get('welcome.recent_itineraries'), 'id'));
    }

    public function test_dashboard_and_appearance_still_require_authentication()
    {
        $this->get(route('dashboard'))->assertRedirect('/login');
        $this->get(route('appearance'))->assertRedirect('/login');

        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
        $this->get(route('appearance'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('settings/appearance'));
    }

    public function test_every_model_has_a_working_factory()
    {
        $this->assertModelExists(User::factory()->create());
        $this->assertModelExists(City::factory()->create());
        $this->assertModelExists(Place::factory()->create());
        $this->assertModelExists(Itinerary::factory()->create());
    }

    public function test_the_seeder_creates_the_samples_without_extra_factory_records()
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        $testUser = User::where('email', 'test@example.com')->sole();
        $this->assertSame(2, User::count());

        foreach (['Barcelona', 'Bangkok'] as $name) {
            $this->assertSame($testUser->id, City::where('name', $name)->sole()->user_id);
        }

        $sagrada = Place::where('name', 'Sagrada Família')->sole();
        $this->assertSame('Barcelona', $sagrada->city->name);
        $this->assertEquals(41.403690, $sagrada->latitude);
        $this->assertEquals(2.174330, $sagrada->longitude);
        $this->assertSame('Bangkok', Place::where('name', 'Wat Arun')->sole()->city->name);
        $this->assertSame(7, Place::whereRelation('city', fn ($query) => $query->whereIn('name', ['Barcelona', 'Bangkok']))->count());

        $samples = Itinerary::where('title', 'like', 'Sample:%')->with('city')->orderBy('id')->get();
        $this->assertSame([
            'Sample: A Weekend in Barcelona' => ['Barcelona', 3],
            'Sample: Barcelona on Foot' => ['Barcelona', 2],
            'Sample: First Day in Bangkok' => ['Bangkok', 3],
        ], $samples->mapWithKeys(fn ($itinerary) => [
            $itinerary->title => [$itinerary->city->name, $itinerary->places()->count()],
        ])->all());
        $this->assertTrue($samples->every(fn ($itinerary) => $itinerary->user_id === $testUser->id));

        // The real travel data is still seeded after the samples.
        $this->assertSame(7, Itinerary::where('title', 'not like', 'Sample:%')->count());
        $this->assertSame(8, City::count());
    }

    public function test_the_seeded_administrator_can_manage_cities_places_and_other_users_itineraries()
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        $admin = User::where('email', 'admin@example.com')->sole();
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue($admin->is_admin);

        $city = City::where('name', 'Barcelona')->sole();
        $place = Place::where('name', 'Sagrada Família')->sole();
        $itinerary = Itinerary::where('title', 'Sample: Barcelona on Foot')->sole();
        $this->assertNotSame($admin->id, $itinerary->user_id);

        $this->get(route('userzone.cities.index'))->assertOk();
        $this->get(route('userzone.cities.edit', $city))->assertOk();
        $this->get(route('userzone.places.index'))->assertOk();
        $this->get(route('userzone.places.edit', $place))->assertOk();
        $this->get(route('userzone.itineraries.show', $itinerary))->assertRedirect(route('itineraries.show', $itinerary));
        $this->get(route('userzone.itineraries.edit', $itinerary))->assertOk();

        $this->put(route('userzone.itineraries.update', $itinerary), [
            'title' => 'Barcelona on Foot, revised',
            'city_id' => $city->id,
            'place_ids' => [$place->id],
        ])->assertRedirect(route('userzone.itineraries.index'));
        $this->assertSame('Barcelona on Foot, revised', $itinerary->fresh()->title);
    }
}
