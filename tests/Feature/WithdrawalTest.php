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

    public function test_ics_creates_a_draft(): void
    {
        $this->actingAs($this->ics)
            ->post(route('withdrawals.store'), [
                'ws_no' => '320301',
                'site_id' => $this->site->id,
                'date' => now()->toDateString(),
                'requested_by_type' => 'subcon',
                'delivered_to' => 'Crew A',
                'items' => [['item_variant_id' => $this->variant->id, 'qty' => 50]],
            ])->assertRedirect();

        $ws = WithdrawalSlip::firstOrFail();
        $this->assertEquals('draft', $ws->status);
    }

    public function test_duplicate_booklet_number_is_rejected(): void
    {
        $payload = [
            'ws_no' => '320301',
            'site_id' => $this->site->id,
            'date' => now()->toDateString(),
            'requested_by_type' => 'subcon',
            'items' => [['item_variant_id' => $this->variant->id, 'qty' => 5]],
        ];

        $this->actingAs($this->ics)->post(route('withdrawals.store'), $payload)->assertRedirect();
        $this->actingAs($this->ics)->post(route('withdrawals.store'), $payload)->assertSessionHasErrors('ws_no');
        $this->assertSame(1, WithdrawalSlip::count());
    }

    public function test_releasing_a_draft_posts_out_and_drops_balance(): void
    {
        $ws = $this->draftSlip();

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

    public function test_legacy_pending_or_approved_slips_can_still_be_released(): void
    {
        $pending = $this->draftSlip('pending_approval');
        $this->actingAs($this->ics)->post(route('withdrawals.release', $pending))->assertRedirect();
        $this->assertEquals('released', $pending->fresh()->status);

        $approved = $this->draftSlip('approved');
        $this->actingAs($this->ics)->post(route('withdrawals.release', $approved))->assertRedirect();
        $this->assertEquals('released', $approved->fresh()->status);
    }

    public function test_user_from_another_site_cannot_release(): void
    {
        $ws = $this->draftSlip();

        $this->actingAs($this->foreignEngineer)->post(route('withdrawals.release', $ws))->assertForbidden();
        $this->assertEquals('draft', $ws->fresh()->status);
        $this->assertEquals(0, \App\Models\StockMovement::where('direction', 'out')->count());
    }

    public function test_released_slip_cannot_be_released_again(): void
    {
        $ws = $this->draftSlip();
        $this->actingAs($this->ics)->post(route('withdrawals.release', $ws));

        $this->actingAs($this->ics)->post(route('withdrawals.release', $ws->fresh()))->assertForbidden();
        $this->assertSame(1, \App\Models\StockMovement::where('direction', 'out')->count());
    }

    public function test_released_slip_can_be_received(): void
    {
        $ws = $this->draftSlip();
        $this->actingAs($this->ics)->post(route('withdrawals.release', $ws));

        $this->actingAs($this->ics)->post(route('withdrawals.receive', $ws))->assertRedirect();
        $this->assertEquals('received', $ws->fresh()->status);
    }

    public function test_show_page_renders_with_workflow_state(): void
    {
        $ws = $this->draftSlip();

        $this->actingAs($this->ics)
            ->get(route('withdrawals.show', $ws))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('withdrawals/show')
                ->where('slip.status', 'draft')
                ->where('can.release', true)
                ->where('can.receive', false));
    }
}
