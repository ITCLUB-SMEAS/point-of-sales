<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Models\ApprovalRequest;
use App\Models\CashierShift;
use App\Models\CashMovement;
use App\Models\Payment;
use App\Models\SaleRefund;
use App\Models\SaleRefundItem;
use App\Models\SaleTransaction;
use App\Models\SaleTransactionItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DailyReport
{
    /**
     * @return array{
     *     date: string,
     *     summary: array{gross_revenue: int, net_revenue: int, cost_total: int, gross_profit: int, cash_revenue: int, qris_revenue: int, refund_total: int, cash_expense_total: int, cash_deposit_total: int, transaction_count: int, void_count: int, pending_approvals: int, cash_variance_total: int},
     *     shifts: array<int, array{id: int, cashier_name: string, opened_at: string, closed_at: ?string, expected_closing_cash: ?int, closing_cash: ?int, cash_variance: ?int}>,
     *     top_products: array<int, array{product_id: int, product_name: string, quantity_sold: int, revenue: int}>
     * }
     */
    public function forDate(string $date): array
    {
        $reportDate = CarbonImmutable::parse($date)->startOfDay();
        $dateString = $reportDate->toDateString();
        $completedSales = SaleTransaction::query()
            ->whereIn('status', [TransactionStatus::Completed, TransactionStatus::PartiallyRefunded])
            ->whereDate('created_at', $dateString);
        $refundTotal = $this->refundTotalFor($dateString);
        $grossRevenue = (int) (clone $completedSales)->sum('total');
        $netRevenue = max(0, $grossRevenue - $refundTotal);
        $costTotal = max(0, $this->soldCostTotalFor($dateString) - $this->refundedCostTotalFor($dateString));

        return [
            'date' => $dateString,
            'summary' => [
                'gross_revenue' => $grossRevenue,
                'net_revenue' => $netRevenue,
                'cost_total' => $costTotal,
                'gross_profit' => $netRevenue - $costTotal,
                'cash_revenue' => $this->paymentTotalFor($dateString, PaymentMethod::Cash),
                'qris_revenue' => $this->paymentTotalFor($dateString, PaymentMethod::Qris),
                'refund_total' => $refundTotal,
                'cash_expense_total' => $this->cashMovementTotalFor($dateString, CashMovementType::Expense),
                'cash_deposit_total' => $this->cashMovementTotalFor($dateString, CashMovementType::Deposit),
                'transaction_count' => (clone $completedSales)->count(),
                'void_count' => SaleTransaction::query()
                    ->where('status', TransactionStatus::Voided)
                    ->whereDate('created_at', $dateString)
                    ->count(),
                'pending_approvals' => ApprovalRequest::query()
                    ->where('status', ApprovalStatus::Pending)
                    ->whereDate('created_at', $dateString)
                    ->count(),
                'cash_variance_total' => (int) CashierShift::query()
                    ->whereDate('opened_at', $dateString)
                    ->sum('cash_variance'),
            ],
            'shifts' => $this->shiftsFor($dateString)->all(),
            'top_products' => $this->topProductsFor($dateString)->all(),
        ];
    }

    private function paymentTotalFor(string $date, PaymentMethod $method): int
    {
        return (int) Payment::query()
            ->where('method', $method)
            ->whereHas('saleTransaction', function ($query) use ($date): void {
                $query
                    ->whereIn('status', [TransactionStatus::Completed, TransactionStatus::PartiallyRefunded])
                    ->whereDate('created_at', $date);
            })
            ->sum('amount');
    }

    private function refundTotalFor(string $date): int
    {
        return (int) SaleRefund::query()
            ->where('status', ApprovalStatus::Approved)
            ->whereDate('decided_at', $date)
            ->sum('amount_total');
    }

    private function soldCostTotalFor(string $date): int
    {
        return (int) SaleTransactionItem::query()
            ->whereHas('saleTransaction', function ($query) use ($date): void {
                $query
                    ->whereIn('status', [TransactionStatus::Completed, TransactionStatus::PartiallyRefunded])
                    ->whereDate('created_at', $date);
            })
            ->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as cost_total')
            ->value('cost_total');
    }

    private function refundedCostTotalFor(string $date): int
    {
        return (int) SaleRefundItem::query()
            ->whereHas('saleRefund', function ($query) use ($date): void {
                $query
                    ->where('status', ApprovalStatus::Approved)
                    ->whereDate('decided_at', $date);
            })
            ->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as cost_total')
            ->value('cost_total');
    }

    private function cashMovementTotalFor(string $date, CashMovementType $type): int
    {
        return (int) CashMovement::query()
            ->where('type', $type)
            ->where('status', ApprovalStatus::Approved)
            ->whereDate('occurred_at', $date)
            ->sum('amount');
    }

    /**
     * @return Collection<int, array{id: int, cashier_name: string, opened_at: string, closed_at: ?string, expected_closing_cash: ?int, closing_cash: ?int, cash_variance: ?int}>
     */
    private function shiftsFor(string $date): Collection
    {
        return CashierShift::query()
            ->with('user')
            ->whereDate('opened_at', $date)
            ->orderBy('opened_at')
            ->get()
            ->map(fn (CashierShift $shift): array => [
                'id' => $shift->id,
                'cashier_name' => $shift->user->name,
                'opened_at' => $shift->opened_at->format('H:i'),
                'closed_at' => $shift->closed_at?->format('H:i'),
                'expected_closing_cash' => $shift->expected_closing_cash,
                'closing_cash' => $shift->closing_cash,
                'cash_variance' => $shift->cash_variance,
            ]);
    }

    /**
     * @return Collection<int, array{product_id: int, product_name: string, quantity_sold: int, revenue: int}>
     */
    private function topProductsFor(string $date): Collection
    {
        return SaleTransactionItem::query()
            ->selectRaw('product_id, product_name, SUM(quantity) as quantity_sold, SUM(subtotal) as revenue')
            ->whereHas('saleTransaction', function ($query) use ($date): void {
                $query
                    ->whereIn('status', [TransactionStatus::Completed, TransactionStatus::PartiallyRefunded])
                    ->whereDate('created_at', $date);
            })
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('quantity_sold')
            ->limit(10)
            ->get()
            ->map(fn (SaleTransactionItem $item): array => [
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product_name,
                'quantity_sold' => (int) $item->quantity_sold,
                'revenue' => (int) $item->revenue,
            ]);
    }
}
