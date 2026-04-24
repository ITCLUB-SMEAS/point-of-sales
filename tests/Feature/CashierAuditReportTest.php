<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalStatus;
use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\ApprovalRequest;
use App\Models\CashierShift;
use App\Models\HeldCart;
use App\Models\Product;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Services\CashMovementService;
use App\Services\CashierAuditReport;
use App\Services\PointOfSaleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierAuditReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_audit_report_collects_cashier_actions_for_a_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-24 09:00:00'));

        $cashier = User::factory()->create(['name' => 'Adit Kasir', 'role' => UserRole::Cashier]);
        $otherCashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = Product::factory()->create(['name' => 'Print Warna', 'price' => 2000, 'stock_quantity' => 20]);
        CashierShift::openFor($cashier, 10000);
        CashierShift::openFor($otherCashier, 10000);

        $sale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 2000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 4000],
            ],
        ]);

        try {
            app(PointOfSaleService::class)->void($sale, $cashier, 'Salah input jumlah');
        } catch (\Throwable) {
            //
        }

        try {
            app(CashMovementService::class)->record($cashier, [
                'type' => CashMovementType::Expense,
                'amount' => 1500,
                'category' => 'Operasional',
                'description' => 'Beli lakban',
            ]);
        } catch (\Throwable) {
            //
        }

        HeldCart::query()->create([
            'user_id' => $cashier->id,
            'name' => 'Draft OSIS',
            'items' => [],
            'total' => 0,
            'created_at' => now()->addMinutes(3),
            'updated_at' => now()->addMinutes(3),
        ]);
        ApprovalRequest::query()->create([
            'requested_by' => $cashier->id,
            'approvable_type' => SaleTransaction::class,
            'approvable_id' => $sale->id,
            'action' => ApprovalAction::RefundTransaction,
            'status' => ApprovalStatus::Pending,
            'reason' => 'Pembeli batal',
            'created_at' => now()->addMinutes(4),
            'updated_at' => now()->addMinutes(4),
        ]);

        app(PointOfSaleService::class)->checkout($otherCashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 2000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 2000],
            ],
        ]);

        $report = app(CashierAuditReport::class)->forCashier($cashier, '2026-04-24');
        $titles = collect($report['entries'])->pluck('title')->all();

        $this->assertSame('Adit Kasir', $report['cashier']['name']);
        $this->assertSame(1, $report['summary']['transactions']);
        $this->assertSame(2, $report['summary']['approval_requests']);
        $this->assertSame(1, $report['summary']['cash_movements']);
        $this->assertSame(1, $report['summary']['drafts']);
        $this->assertSame(4000, $report['summary']['transaction_total']);
        $this->assertContains('Transaksi selesai', $titles);
        $this->assertContains('Request void transaksi', $titles);
        $this->assertContains('Request refund transaksi', $titles);
        $this->assertContains('Kas keluar / setoran', $titles);
        $this->assertContains('Draft keranjang', $titles);

        CarbonImmutable::setTestNow();
    }

    public function test_admin_can_view_cashier_audit_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $cashier = User::factory()->create(['name' => 'Rani Kasir', 'role' => UserRole::Cashier]);
        $product = Product::factory()->create(['name' => 'Fotocopy A4', 'price' => 500, 'stock_quantity' => 20]);

        CashierShift::openFor($cashier, 10000);
        app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 500],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 2000],
            ],
        ]);

        $response = $this
            ->actingAs($admin)
            ->get('/admin/cashier-audit?cashier='.$cashier->id.'&date='.today()->toDateString());

        $response
            ->assertOk()
            ->assertSeeText('Audit Detail per Kasir')
            ->assertSeeText('Rani Kasir')
            ->assertSeeText('Transaksi selesai')
            ->assertSeeText('Rp2.000');
    }

    public function test_cashier_audit_keeps_draft_history_after_draft_is_deleted(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = Product::factory()->create(['name' => 'Print Tugas', 'price' => 1500, 'stock_quantity' => 20]);

        CashierShift::openFor($cashier, 10000);

        $this
            ->actingAs($cashier)
            ->post(route('pos.drafts.store'), [
                'name' => 'Draft Tugas IPA',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                        'unit_price' => 1500,
                    ],
                ],
            ]);

        $heldCart = HeldCart::query()->firstOrFail();

        $this
            ->actingAs($cashier)
            ->delete(route('pos.drafts.destroy', $heldCart));

        $report = app(CashierAuditReport::class)->forCashier($cashier, today()->toDateString());
        $descriptions = collect($report['entries'])->pluck('description')->all();

        $this->assertSame(2, $report['summary']['drafts']);
        $this->assertContains('Draft Tugas IPA dibuat', $descriptions);
        $this->assertContains('Draft Tugas IPA dihapus', $descriptions);
    }
}
