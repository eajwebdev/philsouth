<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemVariant>
 */
class ItemVariantFactory extends Factory
{
    protected $model = ItemVariant::class;

    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-#####')),
            'label' => null,
            'attributes' => null,
            'barcode' => null,
            'uom' => null,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
