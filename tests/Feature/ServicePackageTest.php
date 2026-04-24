<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicePackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_see_active_service_packages_on_pos_page(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $copy = Product::factory()->create(['name' => 'Fotocopy A4 BW', 'price' => 500]);
        $binding = Product::factory()->create(['name' => 'Jilid Mika', 'price' => 3000]);
        $package = ServicePackage::factory()->create([
            'name' => 'Paket Modul 20 Lembar',
            'price' => 13000,
            'is_active' => true,
        ]);
        $package->items()->createMany([
            ['product_id' => $copy->id, 'quantity' => 20, 'unit_price' => 500],
            ['product_id' => $binding->id, 'quantity' => 1, 'unit_price' => 3000],
        ]);

        CashierShift::openFor($cashier, 10000);

        $response = $this
            ->actingAs($cashier)
            ->get(route('pos.index'));

        $response
            ->assertOk()
            ->assertSeeText('Paket Cepat')
            ->assertSeeText('Paket Modul 20 Lembar')
            ->assertSee('data-pos-packages', false)
            ->assertSee('data-add-package', false);
    }
}
