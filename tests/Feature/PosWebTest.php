<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Models\ApprovalRequest;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\User;
use App\Services\PointOfSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pos_redirects_to_filament_login(): void
    {
        $response = $this->get('/pos');

        $response->assertRedirect('/admin/login');
    }

    public function test_cashier_can_view_product_catalog_and_cart_checkout_surface(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        Product::factory()->create([
            'name' => 'Fotocopy A4 BW',
            'price' => 500,
            'unit' => 'lembar',
            'is_active' => true,
        ]);
        Product::factory()->create([
            'name' => 'Print Warna',
            'price' => 2000,
            'unit' => 'lembar',
            'is_active' => true,
        ]);

        CashierShift::openFor($cashier, 10000);

        $response = $this
            ->actingAs($cashier)
            ->get('/pos');

        $response
            ->assertOk()
            ->assertSeeText('Katalog Cepat')
            ->assertSeeText('Fotocopy A4 BW')
            ->assertSeeText('Print Warna')
            ->assertSeeText('Keranjang')
            ->assertSeeText('Tambah')
            ->assertSeeText('Total')
            ->assertSee('data-pos-products', false)
            ->assertSee('data-add-product', false)
            ->assertSee('cart-items', false);
    }

    public function test_pos_no_longer_exposes_print_queue_and_shift_handover_features(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);

        CashierShift::openFor($cashier, 10000);

        $response = $this
            ->actingAs($cashier)
            ->get(route('pos.index'));

        $response
            ->assertOk()
            ->assertDontSeeText('Antrian Print')
            ->assertDontSeeText('Serah Terima Shift')
            ->assertDontSee('pos.print-orders.store', false)
            ->assertDontSee('pos.shift.handovers.store', false);

        $this->assertFalse(\Route::has('pos.print-orders.store'));
        $this->assertFalse(\Route::has('pos.shift.handovers.store'));
    }

    public function test_cashier_can_hold_and_delete_cart_draft(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = Product::factory()->create([
            'name' => 'Print Tugas IPA',
            'price' => 1500,
            'is_active' => true,
        ]);

        CashierShift::openFor($cashier, 10000);

        $holdResponse = $this
            ->actingAs($cashier)
            ->post(route('pos.drafts.store'), [
                'name' => 'Adit 9A',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 3,
                        'unit_price' => 1500,
                        'source_note' => 'WhatsApp',
                    ],
                ],
            ]);

        $holdResponse->assertRedirect(route('pos.index'));
        $this->assertDatabaseHas('held_carts', [
            'user_id' => $cashier->id,
            'name' => 'Adit 9A',
            'total' => 4500,
        ]);

        $heldCartId = (int) \DB::table('held_carts')->value('id');

        $deleteResponse = $this
            ->actingAs($cashier)
            ->delete(route('pos.drafts.destroy', $heldCartId));

        $deleteResponse->assertRedirect(route('pos.index'));
        $this->assertDatabaseMissing('held_carts', [
            'id' => $heldCartId,
        ]);
    }

    public function test_cashier_can_see_held_cart_on_pos_page(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = Product::factory()->create([
            'name' => 'Fotocopy Paket OSIS',
            'price' => 500,
            'is_active' => true,
        ]);

        CashierShift::openFor($cashier, 10000);

        $this
            ->actingAs($cashier)
            ->post(route('pos.drafts.store'), [
                'name' => 'OSIS',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 12,
                        'unit_price' => 500,
                        'source_note' => 'USB',
                    ],
                ],
            ]);

        $response = $this
            ->actingAs($cashier)
            ->get(route('pos.index'));

        $response
            ->assertOk()
            ->assertSeeText('Draft Ditahan')
            ->assertSeeText('OSIS')
            ->assertSeeText('Rp6.000')
            ->assertSee('data-held-carts', false)
            ->assertSee('data-restore-held-cart', false);
    }

    public function test_cashier_can_view_printable_receipt(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = Product::factory()->create([
            'name' => 'Print A4 Warna',
            'type' => ProductType::Service,
            'price' => 2000,
            'stock_quantity' => 25,
        ]);

        CashierShift::openFor($cashier, 0);

        $sale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 2000, 'source_note' => 'WhatsApp'],
            ],
            'payments' => [
                ['method' => PaymentMethod::Qris, 'amount' => 4000, 'reference' => 'QR-123'],
            ],
        ]);

        $response = $this
            ->actingAs($cashier)
            ->get(route('transactions.receipt', $sale));

        $response
            ->assertOk()
            ->assertSeeText($sale->number)
            ->assertSeeText('Print A4 Warna')
            ->assertSeeText('WhatsApp')
            ->assertSeeText('QR-123')
            ->assertSeeText('Rp4.000');
    }

    public function test_cashier_can_request_refund_from_recent_transaction(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $product = Product::factory()->create([
            'name' => 'Pulpen Hitam',
            'price' => 3000,
            'stock_quantity' => 10,
        ]);

        CashierShift::openFor($cashier, 0);

        $sale = app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 3000],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 3000],
            ],
        ]);

        $page = $this
            ->actingAs($cashier)
            ->get(route('pos.index'));

        $page
            ->assertOk()
            ->assertSeeText('Refund')
            ->assertSee(route('transactions.refund', $sale), false);

        $response = $this
            ->actingAs($cashier)
            ->post(route('transactions.refund', $sale), [
                'reason' => 'Pembeli batal',
            ]);

        $response
            ->assertRedirect(route('pos.index'))
            ->assertSessionHas('status', 'Permintaan refund dikirim untuk approval admin/supervisor.');

        $approval = ApprovalRequest::query()->firstOrFail();

        $this->assertSame(ApprovalAction::RefundTransaction, $approval->action);
        $this->assertSame(ApprovalStatus::Pending, $approval->status);
    }

    public function test_cashier_can_view_shift_report_after_closing_shift(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $paper = Product::factory()->create([
            'name' => 'Fotocopy A4 BW',
            'price' => 500,
            'stock_quantity' => 100,
        ]);

        $shift = CashierShift::openFor($cashier, 10000);

        app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $paper->id, 'quantity' => 4, 'unit_price' => 500],
            ],
            'payments' => [
                ['method' => PaymentMethod::Cash, 'amount' => 2000],
            ],
        ]);

        app(PointOfSaleService::class)->checkout($cashier, [
            'items' => [
                ['product_id' => $paper->id, 'quantity' => 2, 'unit_price' => 500],
            ],
            'payments' => [
                ['method' => PaymentMethod::Qris, 'amount' => 1000, 'reference' => 'QR-456'],
            ],
        ]);

        $shift->close(11900);

        $response = $this
            ->actingAs($cashier)
            ->get(route('pos.shifts.report', $shift));

        $response
            ->assertOk()
            ->assertSeeText('Laporan Shift')
            ->assertSeeText('Kasir')
            ->assertSeeText('Rp3.000')
            ->assertSeeText('Rp2.000')
            ->assertSeeText('Rp1.000')
            ->assertSeeText('-Rp100');
    }
}
