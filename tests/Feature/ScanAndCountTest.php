<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Site;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanAndCountTest extends TestCase
{
    use RefreshDatabase;

    protected User $ics;
    protected Site $site;
    protected ItemVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->ics = User::factory()->create();
        $this->ics->assignRole('ics');
        $this->site = Site::factory()->create();
        $this->site->users()->attach($this->ics->id);

        $item = Item::create(['code' => 'CEM-001', 'description' => 'Cement', 'uom' => 'bag']);
        $this->variant = $item->defaultVariant;
        $this->variant->update(['barcode' => 'PS-CEM-001']);

        app(StockService::class)->postMovement($this->site, $this->variant, 'in', 'purchase', 100, ['created_by' => $this->ics->id]);
    }

    public function test_lookup_resolves_a_known_barcode_with_site_balance(): void
    {
        $this->actingAs($this->ics)
            ->getJson(route('scan.lookup', ['barcode' => 'PS-CEM-001', 'site_id' => $this->site->id]))
            ->assertOk()
            ->assertJson(['found' => true, 'balance' => 100])
            ->assertJsonPath('variant.sku', 'CEM-001');
    }

    public function test_lookup_misses_an_unknown_barcode(): void
    {
        $this->actingAs($this->ics)
            ->getJson(route('scan.lookup', ['barcode' => 'NOPE-123']))
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    public function test_physical_count_posts_a_positive_adjustment_for_a_surplus(): void
    {
        // System is 100; counted 112 → +12 adjustment IN.
        $this->actingAs($this->ics)
            ->post(route('inventory.count.store'), [
                'site_id' => $this->site->id,
                'item_variant_id' => $this->variant->id,
                'counted_qty' => 112,
            ])->assertRedirect();

        $this->assertEquals(112, app(StockService::class)->balance($this->site, $this->variant));
        $this->assertDatabaseHas('stock_movements', [
            'site_id' => $this->site->id,
            'item_variant_id' => $this->variant->id,
            'direction' => 'in',
            'source' => 'adjustment',
            'quantity' => 12,
        ]);
    }

    public function test_physical_count_posts_a_negative_adjustment_for_a_shortage(): void
    {
        // System 100; counted 85 → -15 adjustment OUT.
        $this->actingAs($this->ics)
            ->post(route('inventory.count.store'), [
                'site_id' => $this->site->id,
                'item_variant_id' => $this->variant->id,
                'counted_qty' => 85,
            ])->assertRedirect();

        $this->assertEquals(85, app(StockService::class)->balance($this->site, $this->variant));
        $this->assertDatabaseHas('stock_movements', [
            'direction' => 'out',
            'source' => 'adjustment',
            'quantity' => 15,
        ]);
    }

    public function test_no_movement_when_count_matches_system(): void
    {
        $this->actingAs($this->ics)
            ->post(route('inventory.count.store'), [
                'site_id' => $this->site->id,
                'item_variant_id' => $this->variant->id,
                'counted_qty' => 100,
            ])->assertRedirect();

        $this->assertEquals(1, \App\Models\StockMovement::count()); // only the opening IN
    }

    public function test_count_requires_site_access(): void
    {
        $outsider = User::factory()->create();
        $outsider->assignRole('ics');

        $this->actingAs($outsider)
            ->post(route('inventory.count.store'), [
                'site_id' => $this->site->id,
                'item_variant_id' => $this->variant->id,
                'counted_qty' => 50,
            ])->assertForbidden();
    }
}
