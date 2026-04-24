<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_in_increases_product_stock_and_records_movement_and_audit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create([
            'name' => 'Kertas A4',
            'stock_quantity' => 12,
            'is_stock_tracked' => true,
        ]);

        $movement = app(InventoryService::class)->stockIn(
            product: $product,
            user: $admin,
            quantity: 18,
            notes: 'Pembelian awal bulan',
        );

        $this->assertSame(30, $product->fresh()->stock_quantity);
        $this->assertSame(InventoryMovementType::StockIn, $movement->type);
        $this->assertSame(18, $movement->quantity);
        $this->assertSame(30, $movement->stock_after);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'inventory.stock_in',
            'user_id' => $admin->id,
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
        ]);
    }

    public function test_adjustment_sets_physical_stock_and_records_delta(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create([
            'name' => 'Tinta Hitam',
            'stock_quantity' => 40,
            'is_stock_tracked' => true,
        ]);

        $movement = app(InventoryService::class)->adjust(
            product: $product,
            user: $admin,
            countedQuantity: 34,
            notes: 'Stock opname',
        );

        $this->assertSame(34, $product->fresh()->stock_quantity);
        $this->assertSame(InventoryMovementType::Adjustment, $movement->type);
        $this->assertSame(-6, $movement->quantity);
        $this->assertSame(34, $movement->stock_after);
    }

    public function test_stock_operation_rejects_untracked_products(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create([
            'stock_quantity' => 0,
            'is_stock_tracked' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(InventoryService::class)->stockIn($product, $admin, 5, 'Tidak boleh');
    }
}
