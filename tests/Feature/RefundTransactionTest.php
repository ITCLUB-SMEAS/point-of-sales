<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalStatus;
use App\Enums\InventoryMovementType;
use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\ApprovalRequest;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Services\PointOfSaleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_refund_completed_transaction_and_restore_stock(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create([
            'name' => 'Kertas A4',
            'stock_quantity' => 20,
            'is_stock_tracked' => true,
        ]);

        CashierShift::openFor($cashier, 0);

        $sale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 1000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 3000],
            ],
        ]);

        app(PointOfSaleService::class)->refund($sale, $admin, 'Barang salah beli');

        $sale->refresh();

        $this->assertSame(TransactionStatus::Refunded, $sale->status);
        $this->assertSame($admin->id, $sale->refunded_by);
        $this->assertSame('Barang salah beli', $sale->refund_reason);
        $this->assertSame(20, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'user_id' => $admin->id,
            'sale_transaction_id' => $sale->id,
            'type' => InventoryMovementType::RefundReturn,
            'quantity' => 3,
            'stock_after' => 20,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'sale.refunded',
            'user_id' => $admin->id,
            'auditable_type' => SaleTransaction::class,
            'auditable_id' => $sale->id,
        ]);
    }

    public function test_cashier_refund_request_requires_supervisor_approval(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create([
            'stock_quantity' => 10,
            'is_stock_tracked' => true,
        ]);

        CashierShift::openFor($cashier, 0);

        $sale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Qris, 'amount' => 2000, 'reference' => 'QR-REFUND'],
            ],
        ]);

        $this->expectException(AuthorizationException::class);

        try {
            app(PointOfSaleService::class)->refund($sale, $cashier, 'Murid batal beli');
        } finally {
            $approval = ApprovalRequest::query()->firstOrFail();

            $this->assertSame(ApprovalAction::RefundTransaction, $approval->action);
            $this->assertSame(ApprovalStatus::Pending, $approval->status);
            $this->assertSame(TransactionStatus::Completed, $sale->fresh()->status);
            $this->assertSame(8, $product->fresh()->stock_quantity);

            app(PointOfSaleService::class)->approve($approval, $admin);

            $this->assertSame(ApprovalStatus::Approved, $approval->fresh()->status);
            $this->assertSame(TransactionStatus::Refunded, $sale->fresh()->status);
            $this->assertSame(10, $product->fresh()->stock_quantity);
        }
    }

    public function test_refunded_transaction_cannot_be_refunded_again(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create(['stock_quantity' => 5]);

        CashierShift::openFor($cashier, 0);

        $sale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 1000],
            ],
        ]);

        app(PointOfSaleService::class)->refund($sale, $admin, 'Refund pertama');

        app(PointOfSaleService::class)->refund($sale, $admin, 'Refund kedua');

        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_movements', 2);
    }
}
