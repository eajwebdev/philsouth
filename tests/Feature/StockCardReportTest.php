<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Site;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockCardReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_card_reconciles_to_the_live_balance(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $ics = User::factory()->create();
        $ics->assignRole('ics');
        $site = Site::factory()->create();
        $site->users()->attach($ics->id);

        $variant = Item::create(['code' => 'CEM-001', 'description' => 'Cement', 'uom' => 'bag'])->defaultVariant;

        $stock = app(StockService::class);
        $stock->postMovement($site, $variant, 'in', 'purchase', 100, ['created_by' => $ics->id]);
        $stock->postMovement($site, $variant, 'out', 'usage', 30, ['created_by' => $ics->id]);
        $stock->postMovement($site, $variant, 'in', 'warehouse_in', 20, ['created_by' => $ics->id]);

        $liveBalance = $stock->balance($site, $variant);
        $this->assertEquals(90, $liveBalance);

        $this->actingAs($ics)
            ->get(route('reports.stock-card', ['site_id' => $site->id, 'item_variant_id' => $variant->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/stock-card')
                ->has('card.rows', 3)
                ->where('card.header.balance', 90)
                ->where('card.totals.in', 120)
                ->where('card.totals.out', 30)
                ->where('card.rows.2.balance', 90)); // last row's running balance
    }

    public function test_report_is_scoped_to_accessible_sites(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $ics = User::factory()->create();
        $ics->assignRole('ics');
        $foreignSite = Site::factory()->create();
        $variant = Item::create(['code' => 'X', 'description' => 'X', 'uom' => 'pc'])->defaultVariant;

        $this->actingAs($ics)
            ->get(route('reports.stock-card', ['site_id' => $foreignSite->id, 'item_variant_id' => $variant->id]))
            ->assertForbidden();
    }
}
