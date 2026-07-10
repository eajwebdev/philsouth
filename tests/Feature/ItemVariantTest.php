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

class ItemVariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        return $user;
    }

    public function test_creating_an_item_auto_creates_one_default_variant(): void
    {
        $item = Item::create([
            'code' => 'CEM-001',
            'description' => 'Portland Cement 40kg',
            'uom' => 'bag',
        ]);

        $this->assertCount(1, $item->variants);
        $variant = $item->variants->first();
        $this->assertTrue($variant->is_default);
        $this->assertEquals('CEM-001', $variant->sku);
    }

    public function test_admin_can_add_a_variant_to_an_item(): void
    {
        $admin = $this->admin();
        $item = Item::create(['code' => 'STL-DB', 'description' => 'Deformed Bar', 'uom' => 'pc', 'has_variants' => true]);

        $this->actingAs($admin)
            ->post(route('variants.store', $item), [
                'sku' => 'STL-DB-12',
                'label' => '12mm x 6m',
                'attributes' => ['size' => '12mm'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('item_variants', ['sku' => 'STL-DB-12', 'is_default' => false]);
    }

    public function test_setting_a_new_default_unsets_the_previous_one(): void
    {
        $admin = $this->admin();
        $item = Item::create(['code' => 'STL-DB', 'description' => 'Deformed Bar', 'uom' => 'pc', 'has_variants' => true]);
        $original = $item->variants()->where('is_default', true)->first();
        $new = $item->variants()->create(['sku' => 'STL-DB-12', 'label' => '12mm']);

        $this->actingAs($admin)
            ->put(route('variants.default', [$item, $new]))
            ->assertRedirect();

        $this->assertTrue($new->fresh()->is_default);
        $this->assertFalse($original->fresh()->is_default);
    }

    public function test_variant_with_movements_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $item = Item::create(['code' => 'STL-DB', 'description' => 'Deformed Bar', 'uom' => 'pc', 'has_variants' => true]);
        $variant = $item->variants()->create(['sku' => 'STL-DB-12', 'label' => '12mm']);
        $site = Site::factory()->create();
        app(StockService::class)->postMovement($site, $variant, 'in', 'purchase', 10, ['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->delete(route('variants.destroy', [$item, $variant]))
            ->assertRedirect();

        $this->assertDatabaseHas('item_variants', ['id' => $variant->id]);
    }

    public function test_deleting_the_default_promotes_another_variant(): void
    {
        $admin = $this->admin();
        $item = Item::create(['code' => 'STL-DB', 'description' => 'Deformed Bar', 'uom' => 'pc', 'has_variants' => true]);
        $default = $item->variants()->where('is_default', true)->first();
        $other = $item->variants()->create(['sku' => 'STL-DB-12', 'label' => '12mm']);

        $this->actingAs($admin)
            ->delete(route('variants.destroy', [$item, $default]))
            ->assertRedirect();

        $this->assertDatabaseMissing('item_variants', ['id' => $default->id]);
        $this->assertTrue($other->fresh()->is_default);
    }
}
