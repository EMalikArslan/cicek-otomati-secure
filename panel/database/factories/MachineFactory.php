<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Machine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Machine> */
class MachineFactory extends Factory
{
    protected $model = Machine::class;

    public function definition(): array
    {
        return [
            'code' => 'ETM_'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 3, '0', STR_PAD_LEFT),
            'name' => $this->faker->company().' Otomati',
            'location_label' => $this->faker->city(),
            'lat' => $this->faker->latitude(36, 42),
            'lng' => $this->faker->longitude(26, 45),
            'timezone' => 'Europe/Istanbul',
            'slot_count' => 24,
            'status' => 'active',
            'installed_at' => now()->subMonths(3),
        ];
    }
}
