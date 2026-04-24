<?php

namespace Database\Factories;

use App\Models\HeldCart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeldCart>
 */
class HeldCartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->name(),
            'items' => [
                [
                    'product_id' => $this->faker->numberBetween(1, 999),
                    'product_name' => $this->faker->words(3, true),
                    'unit' => 'lembar',
                    'quantity' => 2,
                    'unit_price' => 1000,
                    'source_note' => null,
                ],
            ],
            'total' => 2000,
        ];
    }
}
