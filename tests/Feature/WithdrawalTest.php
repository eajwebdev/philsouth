<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Site;
use App\Models\User;
use App\Models\WithdrawalSlip;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalTest extends TestCase
{
    use RefreshDatabase;

    protected User $ics;
    protected User $engineer;
    protected User $foreignEngineer;
    protected Site $site;
    protected ItemVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->site = Site::factory()->create();

        $this->ics = $this->userWith('ics');
        $this->engineer = $this->userWith('engineer');
        $this->foreignEngineer = $this->userWith('engineer');

        $this->site->users()->attach([$this->ics->id, $this->engineer->id]);
        // foreignEngineer is on a different site
        Site::factory()->create()->users()->attach($this->foreignEngineer->id);

        $item = Item::create(['code' => 'CEM-001', 'description' => 'Cement', 'uom' => 'bag']);
        $this->variant = $item->defaultVariant;

        // Opening stock so releases have something to deduct.
        app(StockService::class)->postMovement($this->site, $this->variant, 'in', 'purchase', 500, ['created_by' => $this->ics->id]);
    }

    protected function userWith(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    protected function draftSlip(string $status = 'draft'): WithdrawalSlip
    {
        $ws = WithdrawalSlip::create([
            'ws_no' => 'WS '.fake()->unique()->numberBetween(320301, 329999),
            'site_id' => $this->site->id,
            'date' => now()->toDateString(),
            'requested_by_type' => 'subcon',
            'delivered_to' => 'Crew A',
            'status' => $status,
            'prepared_by' => $this->ics->id,
            'created_by' => $this->ics->id,
        ]);
        $ws->items()->create(['item_variant_id' => $this->variant->id, 'qty' => 120]);

        return $ws;
    }

    public function test_ics_creates_a_draft_then_submits_it(): void
    {
        $this->actingAs($this->ics)
            ->post(route('withdrawals.store'), [
                'site_id' => $this->site->id,
                'date' => now()->toDateString(),
                'requested_by_type' => 'subcon',
                'delivered_to' => 'Crew A',
                'items' => [['item_variant_id' => $this->variant->id, 'qty' => 50]],
            ])->assertRedirect();

        $ws = WithdrawalSlip::firstOrFail();
        $this->assertEquals('draft', $ws->status);

        $this->actingAs($this->ics)->post(route('withdrawals.submit', $ws))->assertRedirect();
        $this->assertEquals('pending_approval', $ws->fresh()->status);
    }

    public function test_engineer_on_the_site_can_approve(): void
    {
        $ws = $this->draftSlip('pending_approval');

        $this->actingAs($this->engineer)->post(route('withdrawals.approve', $ws))->assertRedirect();

        $this->assertEquals('approved', $ws->fresh()->status);
        $this->assertEquals($this->engineer->id, $ws->fresh()->approved_by);
    }

    public function test_engineer_from_another_site_cannot_approve(): void
    {
        $ws = $this->draftSlip('pending_approval');

        $this->actingAs($this->foreignEngineer)->post(route('withdrawals.approve', $ws))->assertForbidden();
        $this->assertEquals('pending_approval', $ws->fresh()->status);
    }

    public function test_no_release_without_approval(): void
    {
        // A pending (not approved) slip may not be released.
        $pending = $this->draftSlip('pending_approval');
        $this->actingAs($this->ics)->post(route('withdrawals.release', $pending))->assertForbidden();

        // A draft may not be released either.
        $draft = $this->draftSlip('draft');
        $this->actingAs($this->ics)->post(route('withdrawals.release', $draft))->assertForbidden();

        $this->assertEquals(0, \App\Models\StockMovement::where('direction', 'out')->count());
    }

    public function test_releasing_an_approved_slip_posts_out_and_drops_balance(): void
    {
        $ws = $this->draftSlip('approved');

        $before = app(StockService::class)->balance($this->site, $this->variant);

        $this->actingAs($this->ics)->post(route('withdrawals.release', $ws))->assertRedirect();

        $ws->refresh();
        $this->assertEquals('released', $ws->status);
        $this->assertEquals($before - 120, app(StockService::class)->balance($this->site, $this->variant));
        $this->assertDatabaseHas('stock_movements', [
            'site_id' => $this->site->id,
            'item_variant_id' => $this->variant->id,
            'direction' => 'out',
            'source' => 'usage',
            'dr_ws_no' => $ws->ws_no,
            'issued_to' => 'Crew A',
            'quantity' => 120,
        ]);
    }

    public function test_released_slip_can_be_received(): void
    {
        $ws = $this->draftSlip('approved');
        $this->actingAs($this->ics)->post(route('withdrawals.release', $ws));

        $this->actingAs($this->ics)->post(route('withdrawals.receive', $ws))->assertRedirect();
        $this->assertEquals('received', $ws->fresh()->status);
    }

    public function test_show_page_renders_with_workflow_state(): void
    {
        $ws = $this->draftSlip('pending_approval');

        $this->actingAs($this->engineer)
            ->get(route('withdrawals.show', $ws))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('withdrawals/show')
                ->where('slip.status', 'pending_approval')
                ->where('can.approve', true)
                ->where('can.release', false));
    }

    public function test_pending_slip_can_be_rejected_by_engineer(): void
    {
        $ws = $this->draftSlip('pending_approval');

        $this->actingAs($this->engineer)
            ->post(route('withdrawals.reject', $ws), ['reject_reason' => 'Over budget'])
            ->assertRedirect();

        $this->assertEquals('rejected', $ws->fresh()->status);
        $this->assertEquals('Over budget', $ws->fresh()->reject_reason);
    }
}
