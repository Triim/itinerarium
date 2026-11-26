<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Itinerary;
use App\Models\Place;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_are_not_administrators()
    {
        $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'password']);

        $this->assertFalse($user->fresh()->is_admin);

        User::factory()->count(20)->create();

        $this->assertSame(0, User::where('is_admin', true)->count());
    }

    public function test_the_admin_factory_state_creates_an_administrator()
    {
        $this->assertTrue(User::factory()->admin()->create()->fresh()->is_admin);
    }

    public function test_existing_users_survive_the_migration_as_non_administrators()
    {
        $migration = require database_path('migrations/2026_11_25_000003_add_is_admin_to_users_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'is_admin'));

        $users = User::factory()->count(2)->create();
        $itinerary = Itinerary::factory()->for($users[0])->create();
        $itinerary->places()->attach(Place::factory()->for($itinerary->city)->create());
        $tables = ['users', 'itineraries', 'itinerary_place'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()]);

        $migration->up();

        $this->assertEquals($before['users'], DB::table('users')->get()->each(function ($row) {
            $this->assertEquals(0, $row->is_admin);
            unset($row->is_admin);
        }));
        $this->assertEquals($before['itineraries'], DB::table('itineraries')->get());
        $this->assertEquals($before['itinerary_place'], DB::table('itinerary_place')->get());
        $this->assertFalse($users[0]->fresh()->is_admin);
    }

    public function test_is_admin_cannot_be_mass_assigned()
    {
        $this->assertFalse((new User)->isFillable('is_admin'));
    }

    public function test_registration_cannot_assign_is_admin()
    {
        $this->post('/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_admin' => true,
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticated();
        $this->assertFalse(User::sole()->is_admin);
    }

    public function test_profile_updates_cannot_assign_is_admin()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/profile', ['name' => 'Ada', 'email' => $user->email, 'is_admin' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame('Ada', $user->fresh()->name);
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_the_seeder_creates_one_administrator_and_keeps_the_sample_data()
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('is_admin', true)->sole();
        $this->assertSame('Admin', $admin->name);
        $this->assertSame('admin@admin.com', $admin->email);
        $this->assertTrue(Hash::check('password', $admin->password));

        // 3 sample and 7 real itineraries, 2 sample and 6 real cities, 7 sample and 111 real places.
        $testUser = User::where('email', 'test@example.com')->sole();
        $this->assertFalse($testUser->is_admin);
        $this->assertSame(2, User::count());
        $this->assertSame(10, $testUser->itineraries()->count());
        $this->assertSame(8, City::count());
        $this->assertSame(118, Place::count());
        $this->assertDatabaseCount('itineraries', 10);
        $this->assertDatabaseCount('itinerary_place', 8 + 112);
    }

    public function test_the_seeded_administrator_can_log_in_and_manage_other_users_records()
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        $this->post('/login', ['email' => 'admin@admin.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($admin = User::where('email', 'admin@admin.com')->sole());

        $itinerary = Itinerary::where('title', 'Hamburg on foot in eight hours')->sole();
        $this->assertNotSame($admin->id, $itinerary->user_id);
        $this->assertTrue($itinerary->canManage($admin));
        $this->get(route('userzone.itineraries.edit', $itinerary))->assertOk();
    }
}
