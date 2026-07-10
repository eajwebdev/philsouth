<?php

namespace Tests\Feature;

use App\Models\ItemVariant;
use App\Models\Site;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockService $service;
    protected Site $site;
    protected ItemVariant $item;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StockService::class);
        $this->site = Site::factory()->create();
        $this->item = ItemVariant::factory()->create();
        $this->user = User::factory()->create();
        config(['inventory.allow_negative' => false]);
    }

    public function test_in_movement_creates_stock_and_increases_balance(): void
    {
        $movement = $this->service->postMovement($this->site, $this->item, 'in', 'purchase', 100, [
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals(100, (float) $movement->balance_after);
        $this->assertEquals(100, $this->service->balance($this->site, $this->item));
        $this->assertDatabaseHas('site_stock', [
            'site_id' => $this->site->id,
            'item_variant_id' => $this->item->id,
            'balance' => 100,
        ]);
    }

    public function test_out_movement_decreases_balance_and_records_balance_after(): void
    {
        $this->service->postMovement($this->site, $this->item, 'in', 'purchase', 100, ['created_by' => $this->user->id]);
        $out = $this->service->postMovement($this->site, $this->item, 'out', 'usage', 30, ['created_by' => $this->user->id]);

        $this->assertEquals(70, (float) $out->balance_after);
        $this->assertEquals(70, $this->service->balance($this->site, $this->item));
    }

    public function test_out_movement_is_rejected_when_it_would_overdraw(): void
    {
        $this->service->postMovement($this->site, $this->item, 'in', 'purchase', 20, ['created_by' => $this->user->id]);

        $this->expectException(RuntimeException::class);

        $this->service->postMovement($this->site, $this->item, 'out', 'usage', 50, ['created_by' => $this->user->id]);
    }

    public function test_overdraw_leaves_balance_unchanged(): void
    {
        $this->service->postMovement($this->site, $this->item, 'in', 'purchase', 20, ['created_by' => $this->user->id]);

        try {
            $this->service->postMovement($this->site, $this->item, 'out', 'usage', 50, ['created_by' => $this->user->id]);
        } catch (RuntimeException) {
            // expected
        }

        $this->assertEquals(20, $this->service->balance($this->site, $this->item));
        $this->assertEquals(1, StockMovement::count());
    }

    public function test_negative_allowed_when_configured(): void
    {
        config(['inventory.allow_negative' => true]);

        $out = $this->service->postMovement($this->site, $this->item, 'out', 'usage', 40, ['created_by' => $this->user->id]);

        $this->assertEquals(-40, (float) $out->balance_after);
    }

    public function test_zero_or_negative_quantity_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->postMovement($this->site, $this->item, 'in', 'purchase', 0, ['created_by' => $this->user->id]);
    }

    public function test_reference_is_stored_as_morph(): void
    {
        $ref = Site::factory()->create();
        $movement = $this->service->postMovement($this->site, $this->item, 'in', 'purchase', 5, [
            'created_by' => $this->user->id,
            'reference' => $ref,
        ]);

        $this->assertEquals($ref->getMorphClass(), $movement->reference_type);
        $this->assertEquals($ref->id, $movement->reference_id);
    }

    public function test_running_balance_chains_correctly_across_movements(): void
    {
        $qtys = [['in', 100], ['out', 25], ['in', 50], ['out', 60]];
        $expected = [100, 75, 125, 65];

        foreach ($qtys as $i => [$dir, $qty]) {
            $m = $this->service->postMovement(
                $this->site,
                $this->item,
                $dir,
                $dir === 'in' ? 'purchase' : 'usage',
                $qty,
                ['created_by' => $this->user->id],
            );
            $this->assertEquals($expected[$i], (float) $m->balance_after);
        }
    }
}
