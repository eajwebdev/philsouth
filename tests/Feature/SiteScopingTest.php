<?php

namespace Tests\Feature;

use App\Models\DeliveryReceipt;
use App\Models\Item;
use App\Models\Site;
use App\Models\TransferSlip;
use App\Models\User;
use App\Models\WithdrawalSlip;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An ICS may only ever touch sites an admin/engineer assigned them to. These
 * lock in that every data surface denies access to an unassigned site.
 */
class SiteScopingTest extends TestCase
{
    use RefreshDatabase;

    protected User $ics;
    protected Site $mySite;
    protected Site $otherSite;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->ics = User::factory()->create();
        $this->ics->assignRole('ics');

        $this->mySite = Site::factory()->create(['name' => 'My Site']);
        $this->otherSite = Site::factory()->create(['name' => 'Other Site']);
        // ICS is assigned ONLY to mySite.
        $this->mySite->users()->attach($this->ics->id);

        $this->item = Item::create(['code' => 'CEM-001', 'description' => 'Cement', 'uom' => 'bag']);

        // Give the *other* site some stock so there is data to (not) leak.
        app(StockService::class)->postMovement(
            $this->otherSite, $this->item->defaultVariant, 'in', 'purchase', 300,
            ['created_by' => $this->ics->id],
        );
    }

    public function test_inventory_only_lists_assigned_site_stock(): void
    {
        $this->actingAs($this->ics)
            ->get(route('inventory.index'))
            ->assertInertia(fn ($page) => $page
                ->has('stock.data', 0)          // no rows: ICS has no stock on their own site
                ->has('sites', 1)               // picker shows only the assigned site
                ->where('sites.0.name', 'My Site'));
    }

    public function test_inventory_filter_to_unassigned_site_is_forbidden(): void
    {
        $this->actingAs($this->ics)
            ->get(route('inventory.index', ['site_id' => $this->otherSite->id]))
            ->assertForbidden();
    }

    public function test_sites_list_hides_unassigned_sites(): void
    {
        $this->actingAs($this->ics)
            ->get(route('sites.index'))
            ->assertInertia(fn ($page) => $page
                ->has('sites.data', 1)
                ->where('sites.data.0.name', 'My Site'));
    }

    public function test_cannot_open_another_sites_team_page(): void
    {
        $this->actingAs($this->ics)->get(route('sites.team', $this->otherSite))->assertForbidden();
        $this->actingAs($this->ics)->get(route('sites.team', $this->mySite))->assertOk();
    }

    public function test_reports_reject_an_unassigned_site(): void
    {
        $variant = $this->item->defaultVariant;

        $this->actingAs($this->ics)
            ->get(route('reports.stock-card', ['site_id' => $this->otherSite->id, 'item_variant_id' => $variant->id]))
            ->assertForbidden();

        $this->actingAs($this->ics)
            ->get(route('reports.monthly-summary', ['site_id' => $this->otherSite->id]))
            ->assertForbidden();

        $this->actingAs($this->ics)
            ->get(route('reports.stock-card.pdf', ['site_id' => $this->otherSite->id, 'item_variant_id' => $variant->id]))
            ->assertForbidden();
    }

    public function test_scan_lookup_hides_balance_for_unassigned_site(): void
    {
        $variant = $this->item->defaultVariant;

        $data = $this->actingAs($this->ics)
            ->getJson(route('scan.lookup', ['site_id' => $this->otherSite->id, 'variant_id' => $variant->id]))
            ->assertOk()
            ->json();

        $this->assertTrue($data['found']);
        $this->assertArrayNotHasKey('balance', $data); // no cross-site balance leak
    }

    public function test_physical_count_cannot_adjust_an_unassigned_site(): void
    {
        $this->actingAs($this->ics)
            ->post(route('inventory.count.store'), [
                'site_id' => $this->otherSite->id,
                'item_variant_id' => $this->item->defaultVariant->id,
                'counted_qty' => 5,
            ])->assertForbidden();
    }

    public function test_receiving_index_and_show_are_scoped(): void
    {
        $dr = DeliveryReceipt::create([
            'dr_no' => 'DR X', 'site_id' => $this->otherSite->id, 'source' => 'supplier',
            'supplier' => 'ACME', 'received_date' => now()->toDateString(), 'status' => 'draft',
            'created_by' => $this->ics->id,
        ]);

        $this->actingAs($this->ics)->get(route('receiving.index'))
            ->assertInertia(fn ($page) => $page->has('receipts.data', 0));

        $this->actingAs($this->ics)->get(route('receiving.show', $dr))->assertForbidden();
    }

    public function test_withdrawal_index_and_show_are_scoped(): void
    {
        $ws = WithdrawalSlip::create([
            'ws_no' => 'WS X', 'site_id' => $this->otherSite->id, 'date' => now()->toDateString(),
            'requested_by_type' => 'subcon', 'status' => 'draft',
            'prepared_by' => $this->ics->id, 'created_by' => $this->ics->id,
        ]);

        $this->actingAs($this->ics)->get(route('withdrawals.index'))
            ->assertInertia(fn ($page) => $page->has('slips.data', 0));

        $this->actingAs($this->ics)->get(route('withdrawals.show', $ws))->assertForbidden();
        $this->actingAs($this->ics)->get(route('withdrawals.pdf', $ws))->assertForbidden();
    }

    public function test_cannot_create_a_withdrawal_for_an_unassigned_site(): void
    {
        $this->actingAs($this->ics)
            ->post(route('withdrawals.store'), [
                'ws_no' => 'WS Y',
                'site_id' => $this->otherSite->id,
                'date' => now()->toDateString(),
                'requested_by_type' => 'subcon',
                'items' => [['item_variant_id' => $this->item->defaultVariant->id, 'qty' => 5]],
            ])->assertForbidden();

        $this->assertDatabaseMissing('withdrawal_slips', ['ws_no' => 'WS Y']);
    }

    public function test_transfer_show_is_hidden_when_ics_is_on_neither_end(): void
    {
        $third = Site::factory()->create();
        $ts = TransferSlip::create([
            'ts_no' => 'TS X', 'from_site_id' => $this->otherSite->id, 'to_site_id' => $third->id,
            'date' => now()->toDateString(), 'status' => 'draft', 'created_by' => $this->ics->id,
        ]);

        $this->actingAs($this->ics)->get(route('transfers.index'))
            ->assertInertia(fn ($page) => $page->has('transfers.data', 0));

        $this->actingAs($this->ics)->get(route('transfers.show', $ts))->assertForbidden();
    }
}
