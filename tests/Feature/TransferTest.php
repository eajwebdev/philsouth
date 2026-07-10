<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Site;
use App\Models\TransferSlip;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    protected User $icsA;
    protected User $icsB;
    protected Site $siteA;
    protected Site $siteB;
    protected ItemVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->siteA = Site::factory()->create(['code' => 'AAA']);
        $this->siteB = Site::factory()->create(['code' => 'BBB']);

        $this->icsA = $this->userWith('ics');
        $this->icsB = $this->userWith('ics');
        $this->siteA->users()->attach($this->icsA->id);
        $this->siteB->users()->attach($this->icsB->id);

        $item = Item::create(['code' => 'CEM-001', 'description' => 'Cement', 'uom' => 'bag']);
        $this->variant = $item->defaultVariant;

        app(StockService::class)->postMovement($this->siteA, $this->variant, 'in', 'purchase', 300, ['created_by' => $this->icsA->id]);
    }

    protected function userWith(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    protected function draft(string $status = 'draft'): TransferSlip
    {
        $ts = TransferSlip::create([
            'ts_no' => 'TS '.fake()->unique()->numberBetween(22501, 29999),
            'from_site_id' => $this->siteA->id,
            'to_site_id' => $this->siteB->id,
            'date' => now()->toDateString(),
            'status' => $status,
            'created_by' => $this->icsA->id,
        ]);
        $ts->items()->create(['item_variant_id' => $this->variant->id, 'qty' => 80]);

        return $ts;
    }

    protected function balance(Site $site): float
    {
        return app(StockService::class)->balance($site, $this->variant);
    }

    public function test_origin_ics_can_create_a_draft_transfer(): void
    {
        $this->actingAs($this->icsA)
            ->post(route('transfers.store'), [
                'from_site_id' => $this->siteA->id,
                'to_site_id' => $this->siteB->id,
                'date' => now()->toDateString(),
                'items' => [['item_variant_id' => $this->variant->id, 'qty' => 50]],
            ])->assertRedirect();

        $this->assertDatabaseHas('transfer_slips', ['from_site_id' => $this->siteA->id, 'to_site_id' => $this->siteB->id, 'status' => 'draft']);
    }

    public function test_dispatch_posts_out_at_origin_and_sets_in_transit(): void
    {
        $ts = $this->draft();
        $before = $this->balance($this->siteA);

        $this->actingAs($this->icsA)->post(route('transfers.dispatch', $ts))->assertRedirect();

        $this->assertEquals('in_transit', $ts->fresh()->status);
        $this->assertEquals($before - 80, $this->balance($this->siteA));
        $this->assertDatabaseHas('stock_movements', [
            'site_id' => $this->siteA->id,
            'direction' => 'out',
            'source' => 'transfer_out',
            'quantity' => 80,
        ]);
        // Destination unchanged until received.
        $this->assertEquals(0, $this->balance($this->siteB));
    }

    public function test_receive_posts_in_at_destination(): void
    {
        $ts = $this->draft();
        $this->actingAs($this->icsA)->post(route('transfers.dispatch', $ts));

        $this->actingAs($this->icsB)->post(route('transfers.receive', $ts), ['received_by' => 'Iris'])->assertRedirect();

        $ts->refresh();
        $this->assertEquals('received', $ts->status);
        $this->assertEquals(80, $this->balance($this->siteB));
        $this->assertDatabaseHas('stock_movements', [
            'site_id' => $this->siteB->id,
            'direction' => 'in',
            'source' => 'transfer_in',
            'quantity' => 80,
        ]);
    }

    public function test_full_transfer_conserves_total_quantity(): void
    {
        $ts = $this->draft();
        $totalBefore = $this->balance($this->siteA) + $this->balance($this->siteB);

        $this->actingAs($this->icsA)->post(route('transfers.dispatch', $ts));
        $this->actingAs($this->icsB)->post(route('transfers.receive', $ts));

        $this->assertEquals($totalBefore, $this->balance($this->siteA) + $this->balance($this->siteB));
    }

    public function test_only_destination_ics_can_receive(): void
    {
        $ts = $this->draft();
        $this->actingAs($this->icsA)->post(route('transfers.dispatch', $ts));

        // Origin ICS is not on the destination site.
        $this->actingAs($this->icsA)->post(route('transfers.receive', $ts))->assertForbidden();
        $this->assertEquals('in_transit', $ts->fresh()->status);
    }

    public function test_dispatch_is_blocked_when_origin_lacks_balance(): void
    {
        $ts = TransferSlip::create([
            'ts_no' => 'TS 22600',
            'from_site_id' => $this->siteA->id,
            'to_site_id' => $this->siteB->id,
            'date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->icsA->id,
        ]);
        $ts->items()->create(['item_variant_id' => $this->variant->id, 'qty' => 9999]);

        $this->actingAs($this->icsA)->post(route('transfers.dispatch', $ts))
            ->assertSessionHas('error');

        $this->assertEquals('draft', $ts->fresh()->status);
        $this->assertEquals(300, $this->balance($this->siteA));
    }
}
