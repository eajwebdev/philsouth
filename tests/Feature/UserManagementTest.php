<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $super;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->super = User::factory()->create(['name' => 'Root Super']);
        $this->super->assignRole('superadmin');

        $this->admin = User::factory()->create(['name' => 'Ana Admin']);
        $this->admin->assignRole('administrator');
    }

    public function test_admin_does_not_see_superadmin_accounts_in_the_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('users/index')
                ->where('users.data', fn ($users) => collect($users)->pluck('id')->doesntContain($this->super->id)));
    }

    public function test_superadmin_sees_every_account(): void
    {
        $this->actingAs($this->super)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('users.data', fn ($users) => collect($users)->pluck('id')->contains($this->super->id)));
    }

    public function test_admin_cannot_create_a_superadmin_account(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => 'Sneaky Root',
                'email' => 'root@philsouth',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'superadmin',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'root@philsouth']);
    }

    public function test_admin_role_options_exclude_superadmin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertInertia(fn ($page) => $page
                ->where('roles', fn ($roles) => ! collect($roles)->contains('superadmin')));
    }

    public function test_admin_cannot_update_or_delete_a_superadmin(): void
    {
        $this->actingAs($this->admin)
            ->put(route('users.update', $this->super), [
                'name' => 'Downgraded',
                'email' => $this->super->email,
                'role' => 'ics',
            ])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $this->super))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->super->id, 'name' => 'Root Super']);
    }

    public function test_superadmin_can_still_manage_superadmin_accounts(): void
    {
        $other = User::factory()->create();
        $other->assignRole('superadmin');

        $this->actingAs($this->super)
            ->put(route('users.update', $other), [
                'name' => 'Renamed Root',
                'email' => $other->email,
                'role' => 'superadmin',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $other->id, 'name' => 'Renamed Root']);
    }
}
