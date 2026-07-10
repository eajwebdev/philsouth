<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('ITM-####')),
            'description' => ucfirst($this->faker->words(3, true)),
            'uom' => $this->faker->randomElement(['pc', 'kg', 'bag', 'set', 'm', 'L', 'box']),
            'category' => $this->faker->randomElement(['Cement', 'Steel', 'Electrical', 'Plumbing', 'Hardware', 'Finishing']),
            'barcode' => null,
            'is_active' => true,
        ];
    }
}
