<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * is_admin is set only by the seeder or the admin() factory state, never through a form.
 * The seeded administrator is covered by DatabaseSeederTest.
 */
class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_are_not_administrators()
    {
        $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'password']);

        $this->assertFalse($user->fresh()->is_admin);
        $this->assertFalse(User::factory()->create()->fresh()->is_admin);
        $this->assertTrue(User::factory()->admin()->create()->fresh()->is_admin);
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
}
