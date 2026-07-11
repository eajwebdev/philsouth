<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ValuationTest extends TestCase
{
    use RefreshDatabase;

    protected User $ics;
    protected Site $site;
    protected \App\Models\ItemVariant $variant;
    protected StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->ics = User::factory()->create();
        $this->ics->assignRole('ics');
        $this->site = Site::factory()->create();
        $this->site->users()->attach($this->ics->id);
        $this->variant = Item::create(['code' => 'CEM-001', 'description' => 'Cement', 'uom' => 'bag'])->defaultVariant;
        $this->stock = app(StockService::class);
    }

    protected function siteStock(): SiteStock
    {
        return SiteStock::where('site_id', $this->site->id)->where('item_variant_id', $this->variant->id)->first();
    }

    public function test_moving_average_cost_reaverages_on_receipt(): void
    {
        $this->stock->postMovement($this->site, $this->variant, 'in', 'purchase', 100, ['created_by' => $this->ics->id, 'unit_cost' => 10]);
        $this->assertEquals(10.0, (float) $this->siteStock()->avg_cost);

        // 100 @ 10 + 100 @ 20 → 200 @ 15.
        $this->stock->postMovement($this->site, $this->variant, 'in', 'purchase', 100, ['created_by' => $this->ics->id, 'unit_cost' => 20]);
        $this->assertEquals(15.0, (float) $this->siteStock()->avg_cost);
        $this->assertEquals(3000.0, $this->siteStock()->value());
    }

    public function test_issue_keeps_average_and_records_cost_of_goods(): void
    {
        $this->stock->postMovement($this->site, $this->variant, 'in', 'purchase', 100, ['created_by' => $this->ics->id, 'unit_cost' => 12]);

        $out = $this->stock->postMovement($this->site, $this->variant, 'out', 'usage', 40, ['created_by' => $this->ics->id]);

        $this->assertEquals(12.0, (float) $this->siteStock()->avg_cost); // unchanged
        $this->assertEquals(12.0, (float) $out->unit_cost);              // cost of goods issued
        $this->assertEquals(720.0, $this->siteStock()->value());         // 60 @ 12
    }

    public function test_receiving_post_captures_unit_cost(): void
    {
        $dr = \App\Models\DeliveryReceipt::create([
            'dr_no' => 'DR 1', 'site_id' => $this->site->id, 'source' => 'supplier', 'supplier' => 'ACME',
            'received_date' => now()->toDateString(), 'status' => 'draft', 'created_by' => $this->ics->id,
        ]);
        $dr->items()->create(['item_variant_id' => $this->variant->id, 'quantity' => 50, 'unit_cost' => 8.5]);

        $this->actingAs($this->ics)->post(route('receiving.post', $dr))->assertRedirect();

        $this->assertEquals(8.5, (float) $this->siteStock()->avg_cost);
        $this->assertEquals(425.0, $this->siteStock()->value());
    }

    public function test_inventory_index_reports_total_value(): void
    {
        $this->stock->postMovement($this->site, $this->variant, 'in', 'purchase', 10, ['created_by' => $this->ics->id, 'unit_cost' => 100]);

        $this->actingAs($this->ics)->get(route('inventory.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('inventory/index')->where('totalValue', 1000));
    }

    public function test_reorder_lists_low_items_with_suggested_qty(): void
    {
        $this->stock->postMovement($this->site, $this->variant, 'in', 'purchase', 5, ['created_by' => $this->ics->id, 'unit_cost' => 1]);
        $this->siteStock()->update(['min_qty' => 20, 'max_qty' => 50]);

        $this->actingAs($this->ics)->get(route('inventory.reorder'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('inventory/reorder')
                ->has('rows.data', 1)
                ->where('rows.data.0.suggested', 45)); // 50 target - 5 on hand
    }

    public function test_threshold_update_changes_min_max(): void
    {
        $this->stock->postMovement($this->site, $this->variant, 'in', 'purchase', 5, ['created_by' => $this->ics->id, 'unit_cost' => 1]);

        $this->actingAs($this->ics)
            ->put(route('inventory.thresholds', $this->siteStock()), ['min_qty' => 15, 'max_qty' => 40, 'location' => 'Bay A'])
            ->assertRedirect();

        $s = $this->siteStock();
        $this->assertEquals(15.0, (float) $s->min_qty);
        $this->assertEquals(40.0, (float) $s->max_qty);
        $this->assertSame('Bay A', $s->location);
    }

    public function test_bulk_csv_import_creates_items_and_skips_existing(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        Item::create(['code' => 'EXIST-1', 'description' => 'Existing', 'uom' => 'pc']);

        $csv = "code,description,uom,category\nNEW-1,New Item One,pc,Hardware\nEXIST-1,Dup,pc,X\nNEW-2,New Item Two,bag,Cement\n";
        $file = UploadedFile::fake()->createWithContent('items.csv', $csv);

        $this->actingAs($admin)->post(route('items.import'), ['file' => $file])->assertRedirect();

        $this->assertDatabaseHas('items', ['code' => 'NEW-1', 'description' => 'New Item One']);
        $this->assertDatabaseHas('items', ['code' => 'NEW-2', 'uom' => 'bag']);
        $this->assertSame(1, Item::where('code', 'EXIST-1')->count()); // not duplicated
    }
}
