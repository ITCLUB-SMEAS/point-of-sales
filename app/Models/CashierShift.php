<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'status',
    'opening_cash',
    'expected_closing_cash',
    'closing_cash',
    'cash_variance',
    'opened_at',
    'closed_at',
    'notes',
])]
class CashierShift extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShiftStatus::class,
            'opening_cash' => 'integer',
            'expected_closing_cash' => 'integer',
            'closing_cash' => 'integer',
            'cash_variance' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public static function openFor(User $cashier, int $openingCash): self
    {
        return self::query()->create([
            'user_id' => $cashier->id,
            'status' => ShiftStatus::Open,
            'opening_cash' => $openingCash,
            'opened_at' => now(),
        ]);
    }

    public function close(int $closingCash): void
    {
        $cashPayments = Payment::query()
            ->where('method', PaymentMethod::Cash)
            ->whereHas('saleTransaction', function ($query): void {
                $query
                    ->where('cashier_shift_id', $this->id)
                    ->where('status', TransactionStatus::Completed);
            })
            ->sum('amount');
        $cashOut = $this->approvedCashMovements()
            ->whereIn('type', [CashMovementType::Expense->value, CashMovementType::Deposit->value])
            ->sum('amount');

        $expectedClosingCash = $this->opening_cash + $cashPayments - $cashOut;

        $this->update([
            'status' => ShiftStatus::Closed,
            'expected_closing_cash' => $expectedClosingCash,
            'closing_cash' => $closingCash,
            'cash_variance' => $closingCash - $expectedClosingCash,
            'closed_at' => now(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function saleTransactions(): HasMany
    {
        return $this->hasMany(SaleTransaction::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /**
     * @return array{gross_revenue: int, cash_revenue: int, qris_revenue: int, transaction_count: int, cash_expense_total: int, cash_deposit_total: int}
     */
    public function summary(): array
    {
        $completedSales = $this->saleTransactions()
            ->where('status', TransactionStatus::Completed);

        return [
            'gross_revenue' => (int) (clone $completedSales)->sum('total'),
            'cash_revenue' => $this->paymentTotalFor(PaymentMethod::Cash),
            'qris_revenue' => $this->paymentTotalFor(PaymentMethod::Qris),
            'transaction_count' => (clone $completedSales)->count(),
            'cash_expense_total' => $this->cashMovementTotalFor(CashMovementType::Expense),
            'cash_deposit_total' => $this->cashMovementTotalFor(CashMovementType::Deposit),
        ];
    }

    private function approvedCashMovements(): HasMany
    {
        return $this->cashMovements()
            ->where('status', ApprovalStatus::Approved);
    }

    private function paymentTotalFor(PaymentMethod $method): int
    {
        return (int) Payment::query()
            ->where('method', $method)
            ->whereHas('saleTransaction', function ($query): void {
                $query
                    ->where('cashier_shift_id', $this->id)
                    ->where('status', TransactionStatus::Completed);
            })
            ->sum('amount');
    }

    private function cashMovementTotalFor(CashMovementType $type): int
    {
        return (int) $this->approvedCashMovements()
            ->where('type', $type)
            ->sum('amount');
    }
}
