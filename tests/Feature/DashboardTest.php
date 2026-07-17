<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoSeeder::class);
    }

    public function test_administrator_sees_the_company_wide_dashboard(): void
    {
        $admin = User::where('email', 'admin@philsouth')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('role', 'administrator')
                ->has('data.kpis.sites')
                ->has('data.kpis.pending_approvals')
                ->has('data.stock_by_site')
                ->has('data.movement_trend', 6)
                ->has('data.setup_gaps'));
    }

    public function test_engineer_sees_the_approval_focused_dashboard(): void
    {
        $engineer = User::where('email', 'engineer@philsouth')->firstOrFail();

        $this->actingAs($engineer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('role', 'engineer')
                ->has('data.kpis.awaiting_approval')
                ->has('data.pending_queue'));
    }

    public function test_ics_sees_the_operational_dashboard(): void
    {
        $ics = User::where('email', 'ics@philsouth')->firstOrFail();

        $this->actingAs($ics)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('role', 'ics')
                ->has('data.kpis.today_receipts')
                ->has('data.week_flow', 7)
                ->has('data.low_stock_items'));
    }
}
