<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Site;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonthlySummaryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_aggregates_and_reconciles_for_a_closed_month(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $site = Site::factory()->create();
        $variant = Item::create(['code' => 'CEM-001', 'description' => 'Cement', 'uom' => 'bag'])->defaultVariant;

        // Target a fully-past month.
        $target = now()->subMonths(2)->startOfMonth();
        $priorDate = (clone $target)->subDay();          // end of the prior month
        $inMonth = (clone $target)->addDays(10);

        $stock = app(StockService::class);
        // Beginning balance established before the month.
        $stock->postMovement($site, $variant, 'in', 'purchase', 100, ['created_by' => $admin->id, 'movement_date' => $priorDate->toDateString()]);
        // Activity within the target month.
        $stock->postMovement($site, $variant, 'in', 'purchase', 50, ['created_by' => $admin->id, 'movement_date' => $inMonth->toDateString()]);
        $stock->postMovement($site, $variant, 'in', 'transfer_in', 20, ['created_by' => $admin->id, 'movement_date' => $inMonth->toDateString()]);
        $stock->postMovement($site, $variant, 'out', 'usage', 30, ['created_by' => $admin->id, 'movement_date' => $inMonth->toDateString()]);

        $this->actingAs($admin)
            ->get(route('reports.monthly-summary', ['site_id' => $site->id, 'month' => $target->format('Y-m')]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/monthly-summary')
                ->where('summary.is_closed', true)
                ->where('summary.reconciles', true)
                ->has('summary.rows', 1)
                ->where('summary.rows.0.beginning', 100)
                ->where('summary.rows.0.purchases', 50)
                ->where('summary.rows.0.transfer_in', 20)
                ->where('summary.rows.0.usage', 30)
                ->where('summary.rows.0.ending', 140));
    }

    public function test_ending_equals_beginning_plus_in_minus_out(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $site = Site::factory()->create();
        $variant = Item::create(['code' => 'STL', 'description' => 'Bar', 'uom' => 'pc'])->defaultVariant;

        $month = now()->subMonth()->startOfMonth();
        $stock = app(StockService::class);
        $stock->postMovement($site, $variant, 'in', 'purchase', 200, ['created_by' => $admin->id, 'movement_date' => $month->copy()->addDays(2)->toDateString()]);
        $stock->postMovement($site, $variant, 'out', 'loss_damage', 15, ['created_by' => $admin->id, 'movement_date' => $month->copy()->addDays(5)->toDateString()]);

        $this->actingAs($admin)
            ->get(route('reports.monthly-summary', ['site_id' => $site->id, 'month' => $month->format('Y-m')]))
            ->assertInertia(fn ($page) => $page
                ->where('summary.rows.0.beginning', 0)
                ->where('summary.rows.0.total_in', 200)
                ->where('summary.rows.0.total_out', 15)
                ->where('summary.rows.0.loss_damage', 15)
                ->where('summary.rows.0.ending', 185));
    }
}
