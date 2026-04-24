<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('SKU-####'),
            'name' => fake()->randomElement(['Fotocopy A4 BW', 'Print Warna', 'Jilid Spiral', 'Pulpen Hitam']),
            'type' => ProductType::Stock,
            'unit' => 'pcs',
            'price' => fake()->numberBetween(500, 15000),
            'cost' => fake()->numberBetween(250, 10000),
            'is_active' => true,
            'is_stock_tracked' => true,
            'stock_quantity' => fake()->numberBetween(20, 100),
            'minimum_stock' => 5,
        ];
    }
}
