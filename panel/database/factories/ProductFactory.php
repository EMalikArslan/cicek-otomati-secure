<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Tek Gul Buketi', 'Papatya', 'Karisik Aranjman', 'Lale Buketi',
            'Orkide', 'Gerbera', 'Lisyantus', 'Kir Cicegi Buketi',
        ]).' '.$this->faker->unique()->numberBetween(1, 9999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => $this->faker->randomElement(['gul', 'papatya', 'aranjman', 'buket', 'saksi']),
            'default_price_minor' => $this->faker->numberBetween(50, 3000) * 100,
            'is_active' => true,
        ];
    }
}
