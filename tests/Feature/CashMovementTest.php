<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalStatus;
use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\ApprovalRequest;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\User;
use App\Services\CashMovementService;
use App\Services\PointOfSaleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_records_approved_cash_expense_and_deposit_that_reduce_expected_closing_cash(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);
        $shift = CashierShift::openFor($cashier, 10000);

        app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 2000],
            ],
        ]);

        $expense = app(CashMovementService::class)->record($admin, [
            'cashier_shift_id' => $shift->id,
            'type' => CashMovementType::Expense,
            'amount' => 1500,
            'category' => 'Bahan habis pakai',
            'description' => 'Beli plastik laminating',
        ]);

        app(CashMovementService::class)->record($admin, [
            'cashier_shift_id' => $shift->id,
            'type' => CashMovementType::Deposit,
            'amount' => 3000,
            'category' => 'Setoran',
            'description' => 'Setor ke guru piket',
        ]);

        $shift->close(7500);

        $this->assertSame(ApprovalStatus::Approved, $expense->status);
        $this->assertSame(7500, $shift->fresh()->expected_closing_cash);
        $this->assertSame(0, $shift->fresh()->cash_variance);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'cash_movement.approved',
            'user_id' => $admin->id,
        ]);
    }

    public function test_cashier_cash_movement_requires_approval_before_affecting_shift_cash(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $shift = CashierShift::openFor($cashier, 10000);

        $this->expectException(AuthorizationException::class);

        try {
            app(CashMovementService::class)->record($cashier, [
                'cashier_shift_id' => $shift->id,
                'type' => CashMovementType::Expense,
                'amount' => 2000,
                'category' => 'Operasional',
                'description' => 'Beli lakban',
            ]);
        } finally {
            $approval = ApprovalRequest::query()->firstOrFail();

            $this->assertSame(ApprovalAction::CashMovement, $approval->action);
            $this->assertSame(ApprovalStatus::Pending, $approval->status);
            $this->assertDatabaseHas('cash_movements', [
                'user_id' => $cashier->id,
                'cashier_shift_id' => $shift->id,
                'amount' => 2000,
                'status' => ApprovalStatus::Pending,
            ]);

            $shift->close(10000);
            $this->assertSame(10000, $shift->fresh()->expected_closing_cash);

            app(PointOfSaleService::class)->approve($approval, $admin);

            $this->assertSame(ApprovalStatus::Approved, $approval->fresh()->status);
            $this->assertDatabaseHas('cash_movements', [
                'user_id' => $cashier->id,
                'amount' => 2000,
                'status' => ApprovalStatus::Approved,
                'approved_by' => $admin->id,
            ]);
        }
    }

    public function test_cashier_cannot_submit_cash_movement_from_pos_page(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        CashierShift::openFor($cashier, 10000);

        $page = $this
            ->actingAs($cashier)
            ->get(route('pos.index'));

        $page
            ->assertOk()
            ->assertDontSeeText('Kas Keluar / Setoran')
            ->assertDontSee(route('pos.cash-movements.store'), false);

        $response = $this
            ->actingAs($cashier)
            ->post(route('pos.cash-movements.store'), [
                'type' => CashMovementType::Deposit->value,
                'amount' => 5000,
                'category' => 'Setoran',
                'description' => 'Setor tengah hari',
            ]);

        $response->assertForbidden();
    }
}
