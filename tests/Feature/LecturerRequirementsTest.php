<?php

namespace Tests\Feature;

use App\Http\Controllers\Userzone\ItineraryController;
use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Checks for the course's explicit requirements: controller-backed routes, one full resource
 * controller and a factory for every model. The seeded administrator (admin@example.com /
 * password) and the factory-made samples are covered by DatabaseSeederTest.
 */
class LecturerRequirementsTest extends TestCase
{
    use RefreshDatabase;

    /** Routes registered by Laravel itself (the health check and local file serving), not by routes/*.php. */
    private const FRAMEWORK_CLOSURE_URIS = ['up', 'storage/{path}'];

    public function test_no_application_route_is_a_closure()
    {
        foreach (Route::getRoutes() as $route) {
            if (in_array($route->uri(), self::FRAMEWORK_CLOSURE_URIS, true)) {
                continue;
            }

            $this->assertNotSame('Closure', $route->getActionName(), "/{$route->uri()} is a closure.");
        }
    }

    public function test_the_userzone_itinerary_controller_is_a_full_resource_controller()
    {
        foreach (['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'] as $action) {
            $this->assertSame(
                ItineraryController::class.'@'.$action,
                Route::getRoutes()->getByName("userzone.itineraries.{$action}")->getActionName(),
            );
        }
    }

    public function test_dashboard_and_appearance_require_authentication()
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
}
