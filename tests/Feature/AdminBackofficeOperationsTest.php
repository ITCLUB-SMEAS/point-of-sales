<?php

namespace Tests\Feature;

use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\User;
use App\Services\PointOfSaleService;
use App\Services\ShiftRecap;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBackofficeOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_record_cash_movement_from_admin_backoffice(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $shift = CashierShift::openFor($cashier, 10000);

        $page = $this
            ->actingAs($admin)
            ->get('/admin/cash-management');

        $page
            ->assertOk()
            ->assertSeeText('Manajemen Kas')
            ->assertSee(route('admin.cash-management.store'), false);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.cash-management.store'), [
                'cashier_shift_id' => $shift->id,
                'type' => CashMovementType::Deposit->value,
                'amount' => 25000,
                'category' => 'Setoran',
                'description' => 'Setoran kas istirahat pertama',
            ]);

        $response
            ->assertRedirect('/admin/cash-management')
            ->assertSessionHas('status', 'Kas berhasil dicatat.');

        $this->assertDatabaseHas('cash_movements', [
            'cashier_shift_id' => $shift->id,
            'user_id' => $admin->id,
            'approved_by' => $admin->id,
            'type' => CashMovementType::Deposit,
            'amount' => 25000,
            'category' => 'Setoran',
        ]);
    }

    public function test_shift_recap_summarizes_shift_activity(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-24 09:00:00'));

        $cashier = User::factory()->create(['name' => 'Adit Kasir', 'role' => UserRole::Cashier]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create(['price' => 2000, 'stock_quantity' => 20]);
        $shift = CashierShift::openFor($cashier, 10000);

        app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 2000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 4000],
            ],
        ]);
        app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 2000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Qris, 'amount' => 2000, 'reference' => 'QR-1'],
            ],
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.cash-management.store'), [
                'cashier_shift_id' => $shift->id,
                'type' => CashMovementType::Deposit->value,
                'amount' => 3000,
                'category' => 'Setoran',
                'description' => 'Setoran tengah hari',
            ]);

        $recap = app(ShiftRecap::class)->forShift($shift);

        $this->assertSame('Adit Kasir', $recap['cashier_name']);
        $this->assertSame(2, $recap['summary']['transaction_count']);
        $this->assertSame(6000, $recap['summary']['gross_revenue']);
        $this->assertSame(4000, $recap['summary']['cash_revenue']);
        $this->assertSame(2000, $recap['summary']['qris_revenue']);
        $this->assertSame(3000, $recap['summary']['cash_deposit_total']);

        $page = $this
            ->actingAs($admin)
            ->get('/admin/shift-recap?shift='.$shift->id);

        $page
            ->assertOk()
            ->assertSeeText('Rekap Shift')
            ->assertSeeText('Adit Kasir')
            ->assertSeeText('Rp6.000');

        CarbonImmutable::setTestNow();
    }

    public function test_admin_can_export_cashier_audit_to_csv_and_pdf(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
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

        $csv = $this
            ->actingAs($admin)
            ->get(route('admin.cashier-audit.export.csv', [
                'cashier' => $cashier->id,
                'date' => today()->toDateString(),
            ]));

        $csv
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csvContent = $csv->streamedContent();
        $this->assertStringContainsString('Transaksi selesai', $csvContent);
        $this->assertStringContainsString('2000', $csvContent);

        $pdf = $this
            ->actingAs($admin)
            ->get(route('admin.cashier-audit.export.pdf', [
                'cashier' => $cashier->id,
                'date' => today()->toDateString(),
            ]));

        $pdf
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
