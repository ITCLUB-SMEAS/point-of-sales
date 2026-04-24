<?php

namespace App\Filament\Pages;

use App\Services\DailyReport as DailyReportService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class DailyReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Laporan Harian';

    protected static ?string $title = 'Laporan Harian';

    protected string $view = 'filament.pages.daily-report';

    public string $date;

    public function mount(): void
    {
        $this->date = request()->query('date', today()->toDateString());
    }

    /**
     * @return array{
     *     date: string,
     *     summary: array{gross_revenue: int, cash_revenue: int, qris_revenue: int, transaction_count: int, void_count: int, pending_approvals: int, cash_variance_total: int},
     *     shifts: array<int, array{id: int, cashier_name: string, opened_at: string, closed_at: ?string, expected_closing_cash: ?int, closing_cash: ?int, cash_variance: ?int}>,
     *     top_products: array<int, array{product_id: int, product_name: string, quantity_sold: int, revenue: int}>
     * }
     */
    public function report(): array
    {
        return app(DailyReportService::class)->forDate($this->date);
    }
}
