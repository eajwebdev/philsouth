<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_administrator_can_assign_engineer_to_a_site(): void
    {
        $admin = $this->userWithRole('administrator');
        $engineer = $this->userWithRole('engineer');
        $site = Site::factory()->create();

        $this->actingAs($admin)
            ->put(route('sites.engineers', $site), ['engineer_ids' => [$engineer->id]])
            ->assertRedirect();

        $this->assertTrue($engineer->fresh()->canAccessSite($site));
    }

    public function test_engineer_cannot_assign_engineers(): void
    {
        $engineer = $this->userWithRole('engineer');
        $other = $this->userWithRole('engineer');
        $site = Site::factory()->create();
        $site->users()->attach($engineer->id);

        $this->actingAs($engineer)
            ->put(route('sites.engineers', $site), ['engineer_ids' => [$other->id]])
            ->assertForbidden();
    }

    public function test_engineer_can_assign_ics_to_own_site(): void
    {
        $engineer = $this->userWithRole('engineer');
        $ics = $this->userWithRole('ics');
        $site = Site::factory()->create();
        $site->users()->attach($engineer->id);

        $this->actingAs($engineer)
            ->put(route('sites.team.update', $site), ['ics_ids' => [$ics->id]])
            ->assertRedirect();

        $this->assertTrue($ics->fresh()->canAccessSite($site));
    }

    public function test_engineer_is_blocked_from_assigning_ics_to_a_non_owned_site(): void
    {
        $engineer = $this->userWithRole('engineer');
        $ics = $this->userWithRole('ics');
        $ownSite = Site::factory()->create();
        $foreignSite = Site::factory()->create();
        $ownSite->users()->attach($engineer->id);

        $this->actingAs($engineer)
            ->put(route('sites.team.update', $foreignSite), ['ics_ids' => [$ics->id]])
            ->assertForbidden();

        $this->assertFalse($ics->fresh()->canAccessSite($foreignSite));
    }

    public function test_ics_only_sees_assigned_sites(): void
    {
        $ics = $this->userWithRole('ics');
        $assigned = Site::factory()->create(['name' => 'Assigned Site']);
        $hidden = Site::factory()->create(['name' => 'Hidden Site']);
        $assigned->users()->attach($ics->id);

        $this->actingAs($ics)
            ->get(route('sites.index'))
            ->assertInertia(fn ($page) => $page
                ->component('sites/index')
                ->has('sites.data', 1)
                ->where('sites.data.0.name', 'Assigned Site'));

        unset($hidden);
    }

    public function test_superadmin_bypasses_site_scope(): void
    {
        $super = $this->userWithRole('superadmin');
        Site::factory()->count(3)->create();

        $this->assertTrue($super->bypassesSiteScope());
        $this->assertCount(3, $super->accessibleSites());
    }
}
