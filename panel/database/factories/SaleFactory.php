<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Models\Machine;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sale> */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        $product = $this->faker->randomElement(['Papatya', 'Tek Gul Buketi', 'Karisik Aranjman', 'Lale']);

        return [
            'machine_id' => Machine::factory(),
            'slot_no' => $this->faker->numberBetween(1, 24),
            'product_name_snapshot' => $product,
            'product_key' => Sale::normalizeProductKey($product),
            'price_minor' => $this->faker->numberBetween(100, 2000) * 100,
            'status' => SaleStatus::Success,
            'sold_at' => $this->faker->dateTimeBetween('-90 days'),
        ];
    }

    public function suspicious(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => SaleStatus::Suspicious]);
    }

    public function lidFailed(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => SaleStatus::LidFailed]);
    }
}
