<?php

namespace Tests\Feature;

use App\Models\DeliveryReceipt;
use App\Models\Item;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivingTest extends TestCase
{
    use RefreshDatabase;

    protected User $ics;
    protected Site $site;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->ics = User::factory()->create();
        $this->ics->assignRole('ics');
        $this->site = Site::factory()->create();
        $this->site->users()->attach($this->ics->id);
        $this->item = Item::create(['code' => 'CEM-001', 'description' => 'Cement', 'uom' => 'bag']);
    }

    protected function variantId(): int
    {
        return $this->item->defaultVariant->id;
    }

    public function test_ics_can_create_a_draft_delivery_receipt(): void
    {
        $this->actingAs($this->ics)
            ->post(route('receiving.store'), [
                'site_id' => $this->site->id,
                'source' => 'supplier',
                'supplier' => 'ACME Hardware',
                'received_date' => now()->toDateString(),
                'items' => [
                    ['item_variant_id' => $this->variantId(), 'quantity' => 100],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('delivery_receipts', ['site_id' => $this->site->id, 'status' => 'draft']);
        $this->assertDatabaseHas('delivery_receipt_items', ['item_variant_id' => $this->variantId(), 'quantity' => 100]);
    }

    public function test_posting_a_receipt_adds_in_movements_and_updates_balance(): void
    {
        $dr = DeliveryReceipt::create([
            'dr_no' => 'DR 1',
            'site_id' => $this->site->id,
            'source' => 'supplier',
            'supplier' => 'ACME',
            'received_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->ics->id,
        ]);
        $dr->items()->create(['item_variant_id' => $this->variantId(), 'quantity' => 100]);

        $this->actingAs($this->ics)
            ->post(route('receiving.post', $dr))
            ->assertRedirect();

        $this->assertEquals('posted', $dr->fresh()->status);
        $this->assertDatabaseHas('stock_movements', [
            'site_id' => $this->site->id,
            'item_variant_id' => $this->variantId(),
            'direction' => 'in',
            'source' => 'purchase',
            'quantity' => 100,
            'balance_after' => 100,
        ]);
        $this->assertDatabaseHas('site_stock', [
            'site_id' => $this->site->id,
            'item_variant_id' => $this->variantId(),
            'balance' => 100,
        ]);
    }

    public function test_a_posted_receipt_cannot_be_posted_again(): void
    {
        $dr = DeliveryReceipt::create([
            'dr_no' => 'DR 2',
            'site_id' => $this->site->id,
            'source' => 'other_project',
            'received_date' => now()->toDateString(),
            'status' => 'posted',
            'created_by' => $this->ics->id,
        ]);

        $this->actingAs($this->ics)
            ->post(route('receiving.post', $dr))
            ->assertForbidden();
    }

    public function test_ics_from_another_site_cannot_view_receipt(): void
    {
        $dr = DeliveryReceipt::create([
            'dr_no' => 'DR 3',
            'site_id' => $this->site->id,
            'source' => 'supplier',
            'received_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->ics->id,
        ]);

        $other = User::factory()->create();
        $other->assignRole('ics');

        $this->actingAs($other)
            ->get(route('receiving.show', $dr))
            ->assertForbidden();
    }

    public function test_draft_can_be_cancelled(): void
    {
        $dr = DeliveryReceipt::create([
            'dr_no' => 'DR 4',
            'site_id' => $this->site->id,
            'source' => 'supplier',
            'received_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->ics->id,
        ]);

        $this->actingAs($this->ics)
            ->post(route('receiving.cancel', $dr))
            ->assertRedirect();

        $this->assertEquals('cancelled', $dr->fresh()->status);
    }
}
