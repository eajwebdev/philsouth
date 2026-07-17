<?php

namespace Tests\Feature;

use App\Models\DeliveryReceipt;
use App\Models\Item;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    protected User $ics;
    protected Site $site;
    protected \App\Models\ItemVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->ics = User::factory()->create();
        $this->ics->assignRole('ics');
        $this->site = Site::factory()->create();
        $this->site->users()->attach($this->ics->id);
        $this->variant = Item::create(['code' => 'CEM-001', 'description' => 'Cement', 'uom' => 'bag'])->defaultVariant;
    }

    protected function draftReceipt(): DeliveryReceipt
    {
        $dr = DeliveryReceipt::create([
            'dr_no' => 'DR 1', 'site_id' => $this->site->id, 'source' => 'supplier', 'supplier' => 'ACME',
            'received_date' => now()->toDateString(), 'status' => 'draft', 'created_by' => $this->ics->id,
        ]);
        $dr->items()->create(['item_variant_id' => $this->variant->id, 'quantity' => 10]);

        return $dr;
    }

    public function test_posting_a_receipt_geotags_it(): void
    {
        $dr = $this->draftReceipt();

        $this->actingAs($this->ics)->post(route('receiving.post', $dr), [
            'latitude' => 9.80565,
            'longitude' => 122.92541,
            'accuracy_m' => 139,
        ])->assertRedirect();

        $this->assertDatabaseHas('location_stamps', [
            'stampable_type' => $dr->getMorphClass(),
            'stampable_id' => $dr->id,
            'action' => 'posted',
            'user_id' => $this->ics->id,
        ]);

        $stamp = \App\Models\LocationStamp::first();
        $this->assertEquals(9.80565, (float) $stamp->latitude);
        $this->assertEquals(122.92541, (float) $stamp->longitude);
        $this->assertEquals(139.0, (float) $stamp->accuracy_m);
    }

    public function test_action_still_succeeds_with_no_location(): void
    {
        $dr = $this->draftReceipt();

        // Location denied — the post must still go through.
        $this->actingAs($this->ics)->post(route('receiving.post', $dr), [
            'unavailable_reason' => 'denied',
        ])->assertRedirect();

        $this->assertSame('posted', $dr->fresh()->status);
        $this->assertDatabaseHas('location_stamps', [
            'stampable_id' => $dr->id,
            'action' => 'posted',
            'latitude' => null,
            'unavailable_reason' => 'denied',
        ]);
    }

    public function test_action_succeeds_when_no_geo_sent_at_all(): void
    {
        $dr = $this->draftReceipt();

        $this->actingAs($this->ics)->post(route('receiving.post', $dr))->assertRedirect();

        $this->assertSame('posted', $dr->fresh()->status);
        // Nothing to record — no noise row.
        $this->assertSame(0, \App\Models\LocationStamp::count());
    }

    public function test_physical_count_geotags_the_adjustment(): void
    {
        app(\App\Services\StockService::class)->postMovement(
            $this->site, $this->variant, 'in', 'purchase', 50, ['created_by' => $this->ics->id],
        );

        $this->actingAs($this->ics)->post(route('inventory.count.store'), [
            'site_id' => $this->site->id,
            'item_variant_id' => $this->variant->id,
            'counted_qty' => 45,
            'latitude' => 9.80565,
            'longitude' => 122.92541,
            'accuracy_m' => 25.5,
        ])->assertRedirect();

        $this->assertDatabaseHas('location_stamps', ['action' => 'counted', 'user_id' => $this->ics->id]);
    }

    public function test_check_in_records_location(): void
    {
        $this->actingAs($this->ics)->post(route('check-in.store'), [
            'site_id' => $this->site->id,
            'note' => 'Morning arrival',
            'latitude' => 9.80565,
            'longitude' => 122.92541,
            'accuracy_m' => 139,
        ])->assertRedirect();

        $this->assertDatabaseHas('check_ins', [
            'site_id' => $this->site->id,
            'user_id' => $this->ics->id,
            'note' => 'Morning arrival',
        ]);
    }

    public function test_check_in_works_without_a_fix(): void
    {
        $this->actingAs($this->ics)->post(route('check-in.store'), [
            'site_id' => $this->site->id,
            'unavailable_reason' => 'insecure',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('check_ins', [
            'user_id' => $this->ics->id,
            'latitude' => null,
            'unavailable_reason' => 'insecure',
        ]);
    }

    public function test_cannot_check_in_to_an_unassigned_site(): void
    {
        $other = Site::factory()->create();

        $this->actingAs($this->ics)
            ->post(route('check-in.store'), ['site_id' => $other->id])
            ->assertForbidden();
    }

    public function test_invalid_coordinates_are_rejected_on_check_in(): void
    {
        $this->actingAs($this->ics)->post(route('check-in.store'), [
            'site_id' => $this->site->id,
            'latitude' => 999,
            'longitude' => 122.9,
        ])->assertSessionHasErrors('latitude');
    }

    public function test_check_in_page_renders(): void
    {
        $this->actingAs($this->ics)->get(route('check-in.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('check-in/index')->has('sites', 1));
    }
}
