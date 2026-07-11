<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Item;
use App\Models\Site;
use App\Models\User;
use App\Models\WithdrawalSlip;
use App\Services\StockService;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $ics;
    protected User $engineer;
    protected Site $site;
    protected \App\Models\ItemVariant $variant;

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
        $this->variant = Item::create(['code' => 'CEM-001', 'description' => 'Cement', 'uom' => 'bag'])->defaultVariant;
        app(StockService::class)->postMovement($this->site, $this->variant, 'in', 'purchase', 500, ['created_by' => $this->ics->id]);
    }

    protected function draft(): WithdrawalSlip
    {
        $ws = WithdrawalSlip::create([
            'ws_no' => 'WS 1', 'site_id' => $this->site->id, 'date' => now()->toDateString(),
            'requested_by_type' => 'subcon', 'status' => 'draft',
            'prepared_by' => $this->ics->id, 'created_by' => $this->ics->id,
        ]);
        $ws->items()->create(['item_variant_id' => $this->variant->id, 'qty' => 20]);

        return $ws;
    }

    public function test_submit_audits_and_notifies_the_site_engineer(): void
    {
        $ws = $this->draft();

        $this->actingAs($this->ics)->post(route('withdrawals.submit', $ws))->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'withdrawal.submitted', 'user_id' => $this->ics->id]);
        $this->assertSame(1, $this->engineer->fresh()->unreadNotifications()->count());
    }

    public function test_approval_audits_and_notifies_the_preparer(): void
    {
        $ws = $this->draft();
        $ws->update(['status' => 'pending_approval']);

        $this->actingAs($this->engineer)->post(route('withdrawals.approve', $ws))->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'withdrawal.approved', 'user_id' => $this->engineer->id]);
        $this->assertSame(1, $this->ics->fresh()->unreadNotifications()->count());
    }

    public function test_notifications_can_be_marked_read(): void
    {
        $ws = $this->draft();
        $this->actingAs($this->ics)->post(route('withdrawals.submit', $ws));

        $this->assertSame(1, $this->engineer->fresh()->unreadNotifications()->count());

        $this->actingAs($this->engineer)->post(route('notifications.read'))->assertRedirect();

        $this->assertSame(0, $this->engineer->fresh()->unreadNotifications()->count());
    }

    public function test_audit_log_page_is_admin_only(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        AuditLog::record('withdrawal.approved', $this->draft(), 'demo');

        $this->actingAs($admin)->get(route('logs.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('logs/index')->has('logs.data'));

        $this->actingAs($this->ics)->get(route('logs.index'))->assertForbidden();
    }

    public function test_demo_seeder_is_skipped_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // Invoke directly (bypassing the console confirmation the test seeder shows in prod).
        (new DemoSeeder)->run();

        $this->assertDatabaseMissing('users', ['email' => 'super@philsouth.test']);
    }
}
