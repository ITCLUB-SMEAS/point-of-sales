<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\ApprovalRequest;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Services\DashboardMetrics;
use App\Services\PointOfSaleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_and_qris_transactions_reduce_stock_and_close_shift_with_cash_variance(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $paper = Product::factory()->create([
            'name' => 'Fotocopy A4 BW',
            'type' => ProductType::Service,
            'price' => 500,
            'is_stock_tracked' => true,
            'stock_quantity' => 50,
            'minimum_stock' => 10,
        ]);

        $shift = CashierShift::openFor($cashier, 50000);

        $cashSale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                [
                    'product_id' => $paper->id,
                    'quantity' => 10,
                    'unit_price' => 500,
                    'source_note' => 'USB',
                ],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 5000],
            ],
        ]);

        $qrisSale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                [
                    'product_id' => $paper->id,
                    'quantity' => 4,
                    'unit_price' => 500,
                    'source_note' => 'WhatsApp',
                ],
            ],
            'payments' => [
                ['method' => PaymentMethod::Qris, 'amount' => 2000, 'reference' => 'QR-001'],
            ],
        ]);

        $shift->close(54000);

        $this->assertSame(TransactionStatus::Completed, $cashSale->fresh()->status);
        $this->assertSame(TransactionStatus::Completed, $qrisSale->fresh()->status);
        $this->assertSame(36, $paper->fresh()->stock_quantity);
        $this->assertSame(55000, $shift->fresh()->expected_closing_cash);
        $this->assertSame(-1000, $shift->fresh()->cash_variance);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'sale.completed',
            'user_id' => $cashier->id,
            'auditable_type' => SaleTransaction::class,
            'auditable_id' => $cashSale->id,
        ]);
    }

    public function test_cashier_cannot_void_a_transaction_without_supervisor_approval(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create(['stock_quantity' => 20]);

        CashierShift::openFor($cashier, 0);

        $sale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => $product->price],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => $product->price * 2],
            ],
        ]);

        $this->expectException(AuthorizationException::class);

        try {
            app(PointOfSaleService::class)->void($sale, $cashier, 'Salah input jumlah');
        } finally {
            $approval = ApprovalRequest::query()->firstOrFail();

            $this->assertSame(ApprovalAction::VoidTransaction, $approval->action);
            $this->assertSame(ApprovalStatus::Pending, $approval->status);
            $this->assertSame(TransactionStatus::Completed, $sale->fresh()->status);

            app(PointOfSaleService::class)->approve($approval, $admin);

            $this->assertSame(ApprovalStatus::Approved, $approval->fresh()->status);
            $this->assertSame(TransactionStatus::Voided, $sale->fresh()->status);
            $this->assertSame(20, $product->fresh()->stock_quantity);
        }
    }

    public function test_dashboard_metrics_show_daily_revenue_pending_approvals_and_low_stock(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = Product::factory()->create([
            'price' => 1000,
            'stock_quantity' => 7,
            'minimum_stock' => 5,
        ]);

        CashierShift::openFor($cashier, 10000);

        $sale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 1000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Qris, 'amount' => 3000, 'reference' => 'QR-002'],
            ],
        ]);

        try {
            app(PointOfSaleService::class)->void($sale, $cashier, 'Butuh approval');
        } catch (AuthorizationException) {
            //
        }

        $metrics = app(DashboardMetrics::class)->today();

        $this->assertSame(3000, $metrics['gross_revenue']);
        $this->assertSame(3000, $metrics['qris_revenue']);
        $this->assertSame(1, $metrics['pending_approvals']);
        $this->assertSame(1, $metrics['low_stock_products']);
    }
}
