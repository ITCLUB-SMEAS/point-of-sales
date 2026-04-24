<?php

namespace Database\Factories;

use App\Models\ServicePackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePackage>
 */
class ServicePackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Paket Modul 20 Lembar', 'Paket Print Tugas', 'Paket Jilid Cepat']),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->numberBetween(3000, 25000),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
