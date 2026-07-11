<?php

namespace Tests\Feature;

use App\Models\DeliveryReceipt;
use App\Models\Item;
use App\Models\Site;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected User $ics;
    protected User $engineer;
    protected Site $site;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->ics = User::factory()->create();
        $this->ics->assignRole('ics');
        $this->engineer = User::factory()->create();
        $this->engineer->assignRole('engineer');
        $this->site = Site::factory()->create();
        $this->site->users()->attach([$this->ics->id, $this->engineer->id]);
        $this->item = Item::create(['code' => 'CEM-001', 'description' => 'Cement', 'uom' => 'bag']);
    }

    // --- Items can be created by ICS and engineers (not just admin) ---

    public function test_ics_can_create_items(): void
    {
        $this->actingAs($this->ics)
            ->post(route('items.store'), ['code' => 'REB-010', 'description' => 'Rebar 10mm', 'uom' => 'pc'])
            ->assertRedirect();

        $this->assertDatabaseHas('items', ['code' => 'REB-010']);
    }

    public function test_engineer_can_create_items(): void
    {
        $this->actingAs($this->engineer)
            ->post(route('items.store'), ['code' => 'PLY-012', 'description' => 'Plywood 1/2', 'uom' => 'sht'])
            ->assertRedirect();

        $this->assertDatabaseHas('items', ['code' => 'PLY-012']);
    }

    public function test_quick_store_returns_item_with_default_variant_and_autogenerates_code(): void
    {
        $res = $this->actingAs($this->ics)
            ->postJson(route('items.quick-store'), ['description' => 'Jackaline 12 ft', 'uom' => 'pc'])
            ->assertCreated()
            ->json('item');

        $this->assertSame('ITM-0001', $res['code']);
        $this->assertNotEmpty($res['variants']);
        $this->assertTrue((bool) $res['variants'][0]['is_default']);
        // Item is global — visible with 0 stock; no site_stock rows created up front.
        $this->assertDatabaseHas('items', ['description' => 'Jackaline 12 ft']);
    }

    // --- Draft delivery receipt edit + delete ---

    protected function draft(): DeliveryReceipt
    {
        $dr = DeliveryReceipt::create([
            'dr_no' => 'DR 10',
            'site_id' => $this->site->id,
            'source' => 'supplier',
            'supplier' => 'ACME',
            'received_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->ics->id,
        ]);
        $dr->items()->create(['item_variant_id' => $this->item->defaultVariant->id, 'quantity' => 5]);

        return $dr;
    }

    public function test_edit_page_renders_for_draft(): void
    {
        $dr = $this->draft();

        $this->actingAs($this->ics)
            ->get(route('receiving.edit', $dr))
            ->assertOk();
    }

    public function test_ics_can_update_a_draft_receipt(): void
    {
        $dr = $this->draft();

        $this->actingAs($this->ics)
            ->put(route('receiving.update', $dr), [
                'site_id' => $this->site->id,
                'source' => 'supplier',
                'supplier' => 'New Supplier',
                'received_date' => now()->toDateString(),
                'items' => [
                    ['item_variant_id' => $this->item->defaultVariant->id, 'quantity' => 25],
                ],
            ])
            ->assertRedirect(route('receiving.show', $dr));

        $this->assertDatabaseHas('delivery_receipts', ['id' => $dr->id, 'supplier' => 'New Supplier']);
        $this->assertDatabaseHas('delivery_receipt_items', ['delivery_receipt_id' => $dr->id, 'quantity' => 25]);
    }

    public function test_ics_can_delete_a_draft_receipt(): void
    {
        $dr = $this->draft();

        $this->actingAs($this->ics)
            ->delete(route('receiving.destroy', $dr))
            ->assertRedirect(route('receiving.index'));

        $this->assertDatabaseMissing('delivery_receipts', ['id' => $dr->id]);
        $this->assertDatabaseMissing('delivery_receipt_items', ['delivery_receipt_id' => $dr->id]);
    }

    public function test_posted_receipt_cannot_be_updated_or_deleted(): void
    {
        $dr = $this->draft();
        $dr->update(['status' => 'posted']);

        $this->actingAs($this->ics)->put(route('receiving.update', $dr), [])->assertForbidden();
        $this->actingAs($this->ics)->delete(route('receiving.destroy', $dr))->assertForbidden();
    }

    public function test_receipt_from_a_non_site_other_source_posts_as_warehouse_in(): void
    {
        $this->actingAs($this->ics)
            ->post(route('receiving.store'), [
                'site_id' => $this->site->id,
                'source' => 'other',
                'supplier' => 'Client-supplied',
                'received_date' => now()->toDateString(),
                'items' => [['item_variant_id' => $this->item->defaultVariant->id, 'quantity' => 12]],
            ])->assertRedirect();

        $dr = DeliveryReceipt::where('source', 'other')->firstOrFail();
        $this->assertSame('Client-supplied', $dr->supplier);

        $this->actingAs($this->ics)->post(route('receiving.post', $dr))->assertRedirect();

        $this->assertDatabaseHas('stock_movements', [
            'site_id' => $this->site->id,
            'direction' => 'in',
            'source' => 'warehouse_in',
            'remarks' => 'Client-supplied',
        ]);
    }

    public function test_other_source_does_not_require_a_description(): void
    {
        $this->actingAs($this->ics)
            ->post(route('receiving.store'), [
                'site_id' => $this->site->id,
                'source' => 'other',
                'received_date' => now()->toDateString(),
                'items' => [['item_variant_id' => $this->item->defaultVariant->id, 'quantity' => 3]],
            ])->assertRedirect()->assertSessionHasNoErrors();
    }

    // --- PDF report endpoints ---

    protected function postStock(): void
    {
        app(StockService::class)->postMovement(
            $this->site,
            $this->item->defaultVariant,
            'in',
            'purchase',
            50,
            ['movement_date' => now()->toDateString(), 'created_by' => $this->ics->id],
        );
    }

    public function test_stock_card_pdf_streams(): void
    {
        $this->postStock();

        $this->actingAs($this->engineer)
            ->get(route('reports.stock-card.pdf', [
                'site_id' => $this->site->id,
                'item_variant_id' => $this->item->defaultVariant->id,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_stock_card_pdf_respects_date_range(): void
    {
        $this->postStock();

        $this->actingAs($this->engineer)
            ->get(route('reports.stock-card.pdf', [
                'site_id' => $this->site->id,
                'item_variant_id' => $this->item->defaultVariant->id,
                'from' => now()->subDays(7)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_monthly_summary_pdf_streams(): void
    {
        $this->postStock();

        $this->actingAs($this->engineer)
            ->get(route('reports.monthly-summary.pdf', [
                'site_id' => $this->site->id,
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_delivery_receipt_pdf_streams(): void
    {
        $dr = $this->draft();

        $this->actingAs($this->ics)
            ->get(route('receiving.pdf', $dr))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_report_csv_exports_stream(): void
    {
        $this->postStock();

        $stockCsv = $this->actingAs($this->engineer)
            ->get(route('reports.stock-card.csv', [
                'site_id' => $this->site->id,
                'item_variant_id' => $this->item->defaultVariant->id,
            ]))->assertOk();
        $this->assertStringContainsString('text/csv', $stockCsv->headers->get('content-type'));

        $summaryCsv = $this->actingAs($this->engineer)
            ->get(route('reports.monthly-summary.csv', [
                'site_id' => $this->site->id,
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ]))->assertOk();
        $this->assertStringContainsString('text/csv', $summaryCsv->headers->get('content-type'));
    }

    public function test_withdrawal_slip_pdf_streams(): void
    {
        $ws = \App\Models\WithdrawalSlip::create([
            'ws_no' => 'WS 1',
            'site_id' => $this->site->id,
            'date' => now()->toDateString(),
            'requested_by_type' => 'group_a',
            'status' => 'draft',
            'prepared_by' => $this->ics->id,
            'created_by' => $this->ics->id,
        ]);
        $ws->items()->create(['item_variant_id' => $this->item->defaultVariant->id, 'qty' => 5]);

        $this->actingAs($this->ics)
            ->get(route('withdrawals.pdf', $ws))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_transfer_slip_pdf_streams(): void
    {
        $dest = Site::factory()->create();
        $ts = \App\Models\TransferSlip::create([
            'ts_no' => 'TS 1',
            'from_site_id' => $this->site->id,
            'to_site_id' => $dest->id,
            'date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->ics->id,
        ]);
        $ts->items()->create(['item_variant_id' => $this->item->defaultVariant->id, 'qty' => 3, 'unit' => 'bag']);

        $this->actingAs($this->ics)
            ->get(route('transfers.pdf', $ts))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_requires_site_access(): void
    {
        $stranger = User::factory()->create();
        $stranger->assignRole('ics');

        $this->actingAs($stranger)
            ->get(route('reports.stock-card.pdf', [
                'site_id' => $this->site->id,
                'item_variant_id' => $this->item->defaultVariant->id,
            ]))
            ->assertForbidden();
    }
}
