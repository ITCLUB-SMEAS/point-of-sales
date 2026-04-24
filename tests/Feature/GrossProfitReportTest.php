<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\User;
use App\Services\DailyReport;
use App\Services\PointOfSaleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrossProfitReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_report_calculates_cost_total_and_gross_profit_after_partial_refund(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-24 09:00:00'));

        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create([
            'name' => 'Pulpen Hitam',
            'price' => 5000,
            'cost' => 3000,
            'stock_quantity' => 20,
        ]);

        CashierShift::openFor($cashier, 0);

        $sale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 5000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 10000],
            ],
        ]);

        app(PointOfSaleService::class)->partialRefund($sale, $admin, [
            'reason' => 'Retur satu pulpen',
            'items' => [
                ['sale_transaction_item_id' => $sale->items()->firstOrFail()->id, 'quantity' => 1],
            ],
        ]);

        $report = app(DailyReport::class)->forDate('2026-04-24');

        $this->assertSame(10000, $report['summary']['gross_revenue']);
        $this->assertSame(5000, $report['summary']['refund_total']);
        $this->assertSame(5000, $report['summary']['net_revenue']);
        $this->assertSame(3000, $report['summary']['cost_total']);
        $this->assertSame(2000, $report['summary']['gross_profit']);

        CarbonImmutable::setTestNow();
    }
}
