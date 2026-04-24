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
use App\Models\SaleRefund;
use App\Models\User;
use App\Services\PointOfSaleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartialRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_refund_part_of_a_transaction_item_and_restore_only_that_quantity(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $paper = Product::factory()->create([
            'name' => 'Kertas A4',
            'stock_quantity' => 20,
            'is_stock_tracked' => true,
        ]);

        CashierShift::openFor($cashier, 0);

        $sale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $paper->id, 'quantity' => 5, 'unit_price' => 1000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 5000],
            ],
        ]);

        $item = $sale->items()->firstOrFail();

        $refund = app(PointOfSaleService::class)->partialRefund($sale, $admin, [
            'reason' => 'Retur sebagian',
            'items' => [
                ['sale_transaction_item_id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $this->assertSame(ApprovalStatus::Approved, $refund->status);
        $this->assertSame(2000, $refund->amount_total);
        $this->assertSame(TransactionStatus::PartiallyRefunded, $sale->fresh()->status);
        $this->assertSame(17, $paper->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $paper->id,
            'sale_transaction_id' => $sale->id,
            'type' => InventoryMovementType::RefundReturn,
            'quantity' => 2,
            'stock_after' => 17,
        ]);
    }

    public function test_cashier_partial_refund_requires_approval_before_stock_is_restored(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $pen = Product::factory()->create([
            'name' => 'Pulpen Hitam',
            'stock_quantity' => 10,
            'is_stock_tracked' => true,
        ]);

        CashierShift::openFor($cashier, 0);

        $sale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $pen->id, 'quantity' => 3, 'unit_price' => 3000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 9000],
            ],
        ]);

        $item = $sale->items()->firstOrFail();

        $this->expectException(AuthorizationException::class);

        try {
            app(PointOfSaleService::class)->partialRefund($sale, $cashier, [
                'reason' => 'Retur 1 pulpen',
                'items' => [
                    ['sale_transaction_item_id' => $item->id, 'quantity' => 1],
                ],
            ]);
        } finally {
            $approval = ApprovalRequest::query()->firstOrFail();
            $refund = SaleRefund::query()->firstOrFail();

            $this->assertSame(ApprovalAction::PartialRefund, $approval->action);
            $this->assertSame(ApprovalStatus::Pending, $refund->status);
            $this->assertSame(7, $pen->fresh()->stock_quantity);

            app(PointOfSaleService::class)->approve($approval, $admin);

            $this->assertSame(ApprovalStatus::Approved, $refund->fresh()->status);
            $this->assertSame(TransactionStatus::PartiallyRefunded, $sale->fresh()->status);
            $this->assertSame(8, $pen->fresh()->stock_quantity);
        }
    }

    public function test_partial_refund_cannot_exceed_remaining_refundable_quantity(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create(['stock_quantity' => 5]);

        CashierShift::openFor($cashier, 0);

        $sale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 2000],
            ],
        ]);

        $item = $sale->items()->firstOrFail();

        app(PointOfSaleService::class)->partialRefund($sale, $admin, [
            'reason' => 'Retur pertama',
            'items' => [
                ['sale_transaction_item_id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(PointOfSaleService::class)->partialRefund($sale, $admin, [
            'reason' => 'Retur lebih',
            'items' => [
                ['sale_transaction_item_id' => $item->id, 'quantity' => 1],
            ],
        ]);
    }

    public function test_cashier_can_request_partial_refund_from_receipt(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = Product::factory()->create(['name' => 'Map Plastik', 'stock_quantity' => 6]);

        CashierShift::openFor($cashier, 0);

        $sale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 2500],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 5000],
            ],
        ]);

        $item = $sale->items()->firstOrFail();

        $page = $this
            ->actingAs($cashier)
            ->get(route('transactions.receipt', $sale));

        $page
            ->assertOk()
            ->assertSeeText('Retur Item')
            ->assertSee(route('transactions.partial-refund', $sale), false);

        $response = $this
            ->actingAs($cashier)
            ->post(route('transactions.partial-refund', $sale), [
                'reason' => 'Salah beli',
                'items' => [
                    [
                        'sale_transaction_item_id' => $item->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('transactions.receipt', $sale))
            ->assertSessionHas('status', 'Permintaan retur item dikirim untuk approval admin/supervisor.');
    }
}
