<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Models\ApprovalRequest;
use App\Models\Product;
use App\Models\SaleTransaction;

class DashboardMetrics
{
    /**
     * @return array{gross_revenue: int, cash_revenue: int, qris_revenue: int, pending_approvals: int, low_stock_products: int, transaction_count: int}
     */
    public function today(): array
    {
        $completedSales = SaleTransaction::query()
            ->where('status', TransactionStatus::Completed)
            ->whereDate('created_at', today());

        return [
            'gross_revenue' => (int) (clone $completedSales)->sum('total'),
            'cash_revenue' => $this->paymentTotalFor(PaymentMethod::Cash),
            'qris_revenue' => $this->paymentTotalFor(PaymentMethod::Qris),
            'pending_approvals' => ApprovalRequest::query()
                ->where('status', ApprovalStatus::Pending)
                ->count(),
            'low_stock_products' => Product::query()
                ->where('is_stock_tracked', true)
                ->whereColumn('stock_quantity', '<=', 'minimum_stock')
                ->count(),
            'transaction_count' => (clone $completedSales)->count(),
        ];
    }

    private function paymentTotalFor(PaymentMethod $method): int
    {
        return (int) \App\Models\Payment::query()
            ->where('method', $method)
            ->whereHas('saleTransaction', function ($query): void {
                $query
                    ->where('status', TransactionStatus::Completed)
                    ->whereDate('created_at', today());
            })
            ->sum('amount');
    }
}
