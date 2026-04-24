<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Models\CashierShift;
use App\Models\HeldCart;
use App\Models\Payment;
use App\Models\SaleRefund;
use App\Models\SaleTransaction;

class ShiftRecap
{
    /**
     * @return array{
     *     id: int,
     *     cashier_name: string,
     *     opened_at: string,
     *     closed_at: ?string,
     *     summary: array{transaction_count: int, gross_revenue: int, cash_revenue: int, qris_revenue: int, refund_total: int, cash_expense_total: int, cash_deposit_total: int, draft_count: int, expected_closing_cash: ?int, closing_cash: ?int, cash_variance: ?int},
     *     transactions: array<int, array{number: string, status: string, total: int, created_at: string}>
     * }
     */
    public function forShift(CashierShift $shift): array
    {
        $shift->loadMissing(['user', 'saleTransactions']);
        $closedAt = $shift->closed_at ?? now();
        $completedSales = $shift->saleTransactions()
            ->whereIn('status', [TransactionStatus::Completed, TransactionStatus::PartiallyRefunded]);

        return [
            'id' => $shift->id,
            'cashier_name' => $shift->user->name,
            'opened_at' => $shift->opened_at->format('d M Y H:i'),
            'closed_at' => $shift->closed_at?->format('d M Y H:i'),
            'summary' => [
                'transaction_count' => (clone $completedSales)->count(),
                'gross_revenue' => (int) (clone $completedSales)->sum('total'),
                'cash_revenue' => $this->paymentTotalFor($shift, PaymentMethod::Cash),
                'qris_revenue' => $this->paymentTotalFor($shift, PaymentMethod::Qris),
                'refund_total' => $this->refundTotalFor($shift),
                'cash_expense_total' => $this->cashMovementTotalFor($shift, CashMovementType::Expense),
                'cash_deposit_total' => $this->cashMovementTotalFor($shift, CashMovementType::Deposit),
                'draft_count' => HeldCart::query()
                    ->where('user_id', $shift->user_id)
                    ->whereBetween('created_at', [$shift->opened_at, $closedAt])
                    ->count(),
                'expected_closing_cash' => $shift->expected_closing_cash,
                'closing_cash' => $shift->closing_cash,
                'cash_variance' => $shift->cash_variance,
            ],
            'transactions' => $shift->saleTransactions()
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (SaleTransaction $saleTransaction): array => [
                    'number' => $saleTransaction->number,
                    'status' => $saleTransaction->status->value,
                    'total' => $saleTransaction->total,
                    'created_at' => $saleTransaction->created_at->format('H:i'),
                ])
                ->all(),
        ];
    }

    private function paymentTotalFor(CashierShift $shift, PaymentMethod $method): int
    {
        return (int) Payment::query()
            ->where('method', $method)
            ->whereHas('saleTransaction', function ($query) use ($shift): void {
                $query
                    ->where('cashier_shift_id', $shift->id)
                    ->whereIn('status', [TransactionStatus::Completed, TransactionStatus::PartiallyRefunded]);
            })
            ->sum('amount');
    }

    private function refundTotalFor(CashierShift $shift): int
    {
        return (int) SaleRefund::query()
            ->where('status', ApprovalStatus::Approved)
            ->whereHas('saleTransaction', function ($query) use ($shift): void {
                $query->where('cashier_shift_id', $shift->id);
            })
            ->sum('amount_total');
    }

    private function cashMovementTotalFor(CashierShift $shift, CashMovementType $type): int
    {
        return (int) $shift->cashMovements()
            ->where('type', $type)
            ->where('status', ApprovalStatus::Approved)
            ->sum('amount');
    }
}
