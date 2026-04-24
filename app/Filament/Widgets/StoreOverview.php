<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StoreOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $metrics = app(DashboardMetrics::class)->today();

        return [
            Stat::make('Omzet Hari Ini', 'Rp'.number_format($metrics['gross_revenue'], 0, ',', '.')),
            Stat::make('QRIS Hari Ini', 'Rp'.number_format($metrics['qris_revenue'], 0, ',', '.')),
            Stat::make('Approval Pending', (string) $metrics['pending_approvals']),
            Stat::make('Stok Menipis', (string) $metrics['low_stock_products']),
        ];
    }
}
