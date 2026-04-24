<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin Guru',
            'email' => 'admin@sekolah.test',
            'role' => UserRole::Admin,
        ]);

        User::factory()->create([
            'name' => 'Kasir Murid',
            'email' => 'kasir@sekolah.test',
            'role' => UserRole::Cashier,
        ]);

        Product::query()->create([
            'sku' => 'FC-A4-BW',
            'name' => 'Fotocopy A4 BW',
            'type' => ProductType::Service,
            'unit' => 'lembar',
            'price' => 500,
            'is_active' => true,
            'is_stock_tracked' => true,
            'stock_quantity' => 1000,
            'minimum_stock' => 100,
        ]);

        Product::query()->create([
            'sku' => 'PRINT-A4-WARNA',
            'name' => 'Print A4 Warna',
            'type' => ProductType::Service,
            'unit' => 'lembar',
            'price' => 2000,
            'is_active' => true,
            'is_stock_tracked' => true,
            'stock_quantity' => 500,
            'minimum_stock' => 50,
        ]);

        Product::query()->create([
            'sku' => 'PULPEN-HITAM',
            'name' => 'Pulpen Hitam',
            'type' => ProductType::Stock,
            'unit' => 'pcs',
            'price' => 3000,
            'cost' => 2000,
            'is_active' => true,
            'is_stock_tracked' => true,
            'stock_quantity' => 30,
            'minimum_stock' => 5,
        ]);
    }
}
