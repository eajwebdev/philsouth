<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $engineer;
    protected User $ics;
    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->engineer = User::factory()->create();
        $this->engineer->assignRole('engineer');
        $this->ics = User::factory()->create();
        $this->ics->assignRole('ics');
        $this->site = Site::factory()->create();
        $this->site->users()->attach([$this->engineer->id, $this->ics->id]);
    }

    public function test_engineer_and_ics_can_add_roster_employees(): void
    {
        foreach ([$this->engineer, $this->ics] as $actor) {
            $this->actingAs($actor)
                ->post(route('employees.store', $this->site), ['name' => 'Ramon Cruz', 'position' => 'Foreman'])
                ->assertRedirect();
        }

        $this->assertDatabaseHas('employees', ['site_id' => $this->site->id, 'name' => 'Ramon Cruz', 'position' => 'Foreman']);
    }

    public function test_engineer_cannot_manage_roster_of_unassigned_site(): void
    {
        $other = Site::factory()->create();

        $this->actingAs($this->engineer)
            ->post(route('employees.store', $other), ['name' => 'X'])
            ->assertForbidden();
    }

    public function test_engineer_grants_scoped_page_access_and_login_works(): void
    {
        $emp = $this->site->employees()->create(['name' => 'Carlo Reyes', 'position' => 'Foreman']);

        $this->actingAs($this->engineer)
            ->post(route('employees.access.grant', $emp), [
                'email' => 'carlo@philsouth.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'pages' => ['inventory.view', 'withdrawal.create'],
            ])->assertRedirect();

        $emp->refresh();
        $this->assertNotNull($emp->user_id);

        $login = $emp->user;
        $this->assertTrue($login->hasRole('staff'));
        $this->assertTrue($login->can('inventory.view'));
        $this->assertTrue($login->can('withdrawal.create'));
        $this->assertFalse($login->can('reports.view'));
        // Given a login gets attached to the site it works for.
        $this->assertTrue($login->fresh()->canAccessSite($this->site));
    }

    public function test_updating_access_replaces_the_granted_pages(): void
    {
        $emp = $this->site->employees()->create(['name' => 'Bert Santos']);
        $this->actingAs($this->engineer)->post(route('employees.access.grant', $emp), [
            'email' => 'bert@philsouth.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'pages' => ['inventory.view'],
        ]);

        $this->actingAs($this->engineer)->put(route('employees.access.update', $emp->refresh()), [
            'pages' => ['reports.view'],
        ])->assertRedirect();

        $login = $emp->user->fresh();
        $this->assertTrue($login->can('reports.view'));
        $this->assertFalse($login->can('inventory.view'));
    }

    public function test_transfer_moves_roster_and_login_site_assignment(): void
    {
        $dest = Site::factory()->create();
        $emp = $this->site->employees()->create(['name' => 'Ramon Cruz', 'position' => 'Foreman']);
        // Give them a login (attached to the origin site).
        $this->actingAs($this->engineer)->post(route('employees.access.grant', $emp), [
            'email' => 'ramon@philsouth.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'pages' => ['inventory.view'],
        ]);
        $login = $emp->refresh()->user;
        $this->assertTrue($login->fresh()->canAccessSite($this->site));

        $this->actingAs($this->engineer)
            ->post(route('employees.transfer', $emp), ['to_site_id' => $dest->id])
            ->assertRedirect();

        $this->assertSame($dest->id, $emp->refresh()->site_id);
        // Login's site assignment followed: now the destination, no longer the origin.
        $this->assertTrue($login->fresh()->canAccessSite($dest));
        $this->assertFalse($login->fresh()->canAccessSite($this->site));
    }

    public function test_cannot_transfer_from_a_site_you_dont_manage(): void
    {
        $other = Site::factory()->create();
        $emp = $other->employees()->create(['name' => 'Stranger']);

        $this->actingAs($this->engineer)
            ->post(route('employees.transfer', $emp), ['to_site_id' => $this->site->id])
            ->assertForbidden();
    }

    public function test_ics_cannot_grant_logins(): void
    {
        $emp = $this->site->employees()->create(['name' => 'Nena Villar']);

        $this->actingAs($this->ics)
            ->post(route('employees.access.grant', $emp), [
                'email' => 'nena@philsouth.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'pages' => ['inventory.view'],
            ])->assertForbidden();
    }

    public function test_revoke_keeps_roster_but_removes_login(): void
    {
        $emp = $this->site->employees()->create(['name' => 'Danny Lim']);
        $this->actingAs($this->engineer)->post(route('employees.access.grant', $emp), [
            'email' => 'danny@philsouth.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'pages' => ['inventory.view'],
        ]);
        $userId = $emp->refresh()->user_id;
        $this->assertNotNull($userId);

        $this->actingAs($this->engineer)
            ->delete(route('employees.access.revoke', $emp))
            ->assertRedirect();

        $this->assertNull($emp->refresh()->user_id);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertDatabaseHas('employees', ['id' => $emp->id, 'name' => 'Danny Lim']);
    }
}
