<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\User;
use App\Services\DailyReport;
use App\Services\PointOfSaleService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_report_summarizes_revenue_payments_shifts_voids_and_top_products(): void
    {
        $reportDate = CarbonImmutable::parse('2026-04-24 09:00:00');
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $paper = Product::factory()->create(['name' => 'Fotocopy A4 BW', 'price' => 500, 'stock_quantity' => 200]);
        $pen = Product::factory()->create(['name' => 'Pulpen Hitam', 'price' => 3000, 'stock_quantity' => 50]);

        CarbonImmutable::setTestNow($reportDate);
        $shift = CashierShift::openFor($cashier, 10000);

        app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $paper->id, 'quantity' => 10, 'unit_price' => 500],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 5000],
            ],
        ]);

        app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $pen->id, 'quantity' => 2, 'unit_price' => 3000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Qris, 'amount' => 6000, 'reference' => 'QR-789'],
            ],
        ]);

        $voidedSale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $paper->id, 'quantity' => 4, 'unit_price' => 500],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 2000],
            ],
        ]);

        app(PointOfSaleService::class)->void($voidedSale, User::factory()->create(['role' => UserRole::Admin]), 'Batal');
        $shift->close(14900);

        CarbonImmutable::setTestNow($reportDate->subDay());
        $oldShift = CashierShift::openFor($cashier, 0);
        app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $pen->id, 'quantity' => 1, 'unit_price' => 3000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 3000],
            ],
        ]);
        $oldShift->close(3000);

        CarbonImmutable::setTestNow();

        $report = app(DailyReport::class)->forDate($reportDate->toDateString());

        $this->assertSame('2026-04-24', $report['date']);
        $this->assertSame(11000, $report['summary']['gross_revenue']);
        $this->assertSame(5000, $report['summary']['cash_revenue']);
        $this->assertSame(6000, $report['summary']['qris_revenue']);
        $this->assertSame(2, $report['summary']['transaction_count']);
        $this->assertSame(1, $report['summary']['void_count']);
        $this->assertSame(-100, $report['summary']['cash_variance_total']);
        $this->assertSame(TransactionStatus::Voided, $voidedSale->fresh()->status);
        $this->assertCount(1, $report['shifts']);
        $this->assertSame('Fotocopy A4 BW', $report['top_products'][0]['product_name']);
        $this->assertSame(10, $report['top_products'][0]['quantity_sold']);
        $this->assertSame(5000, $report['top_products'][0]['revenue']);
    }

    public function test_admin_can_view_daily_report_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this
            ->actingAs($admin)
            ->get('/admin/daily-report');

        $response
            ->assertOk()
            ->assertSeeText('Laporan Harian')
            ->assertSeeText('Omzet Total')
            ->assertSeeText('Export CSV')
            ->assertSeeText('Export PDF')
            ->assertSeeText(now()->toDateString());
    }

    public function test_admin_panel_loads_custom_theme_for_daily_report_styles(): void
    {
        $this->assertSame(
            'resources/css/filament/admin/theme.css',
            Filament::getPanel('admin')->getViteTheme(),
        );
    }

    public function test_admin_can_export_daily_report_as_csv(): void
    {
        $reportDate = CarbonImmutable::parse('2026-04-24 09:00:00');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = Product::factory()->create(['name' => 'Fotocopy A4 BW', 'price' => 500]);

        CarbonImmutable::setTestNow($reportDate);
        CashierShift::openFor($cashier, 10000);
        app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 500],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 5000],
            ],
        ]);
        CarbonImmutable::setTestNow();

        $response = $this
            ->actingAs($admin)
            ->get('/admin/daily-report/export/csv?date=2026-04-24');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('"Laporan Harian",2026-04-24', $content);
        $this->assertStringContainsString('"Omzet Total",5000', $content);
        $this->assertStringContainsString('"Fotocopy A4 BW",10,5000', $content);
    }

    public function test_admin_can_export_daily_report_as_pdf(): void
    {
        $reportDate = CarbonImmutable::parse('2026-04-24 09:00:00');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = Product::factory()->create(['name' => 'Print Warna', 'price' => 2000]);

        CarbonImmutable::setTestNow($reportDate);
        CashierShift::openFor($cashier, 10000);
        app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 2000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Qris, 'amount' => 4000, 'reference' => 'QR-789'],
            ],
        ]);
        CarbonImmutable::setTestNow();

        $response = $this
            ->actingAs($admin)
            ->get('/admin/daily-report/export/pdf?date=2026-04-24');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
