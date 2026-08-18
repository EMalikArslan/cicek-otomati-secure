<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SlotState;
use App\Models\Machine;
use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Slot> */
class SlotFactory extends Factory
{
    protected $model = Slot::class;

    public function definition(): array
    {
        return [
            'machine_id' => Machine::factory(),
            'slot_no' => $this->faker->unique()->numberBetween(1, 24),
            'is_enabled' => true,
            'product_name' => $this->faker->randomElement(['Papatya', 'Tek Gul Buketi', 'Karisik Aranjman']),
            'price_minor' => $this->faker->numberBetween(100, 2000) * 100,
            'state' => SlotState::Full,
            'last_restock_at' => now()->subDays($this->faker->numberBetween(0, 10)),
        ];
    }

    public function empty(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => SlotState::Empty,
            'product_name' => null,
            'price_minor' => 0,
        ]);
    }
}
