<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'code' => 'SITE-'.strtoupper($this->faker->unique()->bothify('??##')),
            'name' => $this->faker->company().' Project',
            'address' => $this->faker->address(),
            'is_active' => true,
        ];
    }
}
