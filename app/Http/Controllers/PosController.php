<?php

namespace App\Http\Controllers;

use App\Enums\ShiftStatus;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\CloseShiftRequest;
use App\Http\Requests\OpenShiftRequest;
use App\Http\Requests\PartialRefundRequest;
use App\Http\Requests\RefundTransactionRequest;
use App\Http\Requests\StoreCashMovementRequest;
use App\Http\Requests\StoreHeldCartRequest;
use App\Http\Requests\VoidTransactionRequest;
use App\Models\AuditLog;
use App\Models\CashierShift;
use App\Models\HeldCart;
use App\Models\Product;
use App\Models\SaleTransaction;
use App\Models\ServicePackage;
use App\Services\CashMovementService;
use App\Services\DashboardMetrics;
use App\Services\PointOfSaleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(Request $request, DashboardMetrics $dashboardMetrics): View
    {
        $user = $request->user();
        $currentShift = CashierShift::query()
            ->where('user_id', $user->id)
            ->where('status', ShiftStatus::Open)
            ->latest('id')
            ->first();

        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $servicePackages = ServicePackage::query()
            ->with(['items.product'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('pos.index', [
            'products' => $products,
            'servicePackages' => $servicePackages,
            'posProducts' => $products
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'type' => $product->type->value,
                    'unit' => $product->unit,
                    'price' => $product->price,
                    'is_stock_tracked' => $product->is_stock_tracked,
                    'stock_quantity' => $product->stock_quantity,
                    'minimum_stock' => $product->minimum_stock,
                ])
                ->values(),
            'posPackages' => $servicePackages
                ->map(fn (ServicePackage $servicePackage): array => [
                    'id' => $servicePackage->id,
                    'name' => $servicePackage->name,
                    'description' => $servicePackage->description,
                    'price' => $servicePackage->price,
                    'items' => $servicePackage->items
                        ->filter(fn ($item): bool => $item->product !== null)
                        ->map(fn ($item): array => [
                            'product_id' => $item->product_id,
                            'product_name' => $item->product->name,
                            'unit' => $item->product->unit,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                        ])
                        ->values(),
                ])
                ->values(),
            'heldCarts' => HeldCart::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->limit(10)
                ->get(),
            'currentShift' => $currentShift,
            'cashMovements' => $currentShift
                ? $currentShift->cashMovements()->latest('id')->limit(5)->get()
                : collect(),
            'recentSales' => SaleTransaction::query()
                ->with(['payments'])
                ->where('cashier_id', $user->id)
                ->latest('id')
                ->limit(8)
                ->get(),
            'metrics' => $dashboardMetrics->today(),
        ]);
    }

    public function storeDraft(StoreHeldCartRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $products = Product::query()
            ->whereKey(collect($validated['items'])->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $items = collect($validated['items'])
            ->map(function (array $item) use ($products): array {
                $product = $products->get((int) $item['product_id']);
                $quantity = (int) $item['quantity'];
                $unitPrice = (int) $item['unit_price'];

                return [
                    'product_id' => (int) $item['product_id'],
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'source_note' => $item['source_note'] ?? null,
                ];
            })
            ->values()
            ->all();

        $heldCart = HeldCart::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'items' => $items,
            'total' => collect($items)->sum(fn (array $item): int => $item['quantity'] * $item['unit_price']),
        ]);
        $this->auditDraft('held_cart.created', $request, $heldCart);

        return redirect()->route('pos.index')->with('status', 'Keranjang ditahan sebagai draft.');
    }

    public function destroyDraft(Request $request, HeldCart $heldCart): RedirectResponse
    {
        if ($heldCart->user_id !== $request->user()->id && ! $request->user()->canApproveSensitiveActions()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $this->auditDraft('held_cart.deleted', $request, $heldCart);
        $heldCart->delete();

        return redirect()->route('pos.index')->with('status', 'Draft keranjang dihapus.');
    }

    public function openShift(OpenShiftRequest $request): RedirectResponse
    {
        $user = $request->user();

        $hasOpenShift = CashierShift::query()
            ->where('user_id', $user->id)
            ->where('status', ShiftStatus::Open)
            ->exists();

        if (! $hasOpenShift) {
            CashierShift::openFor($user, (int) $request->validated('opening_cash'));
        }

        return redirect()->route('pos.index')->with('status', 'Shift kasir dibuka.');
    }

    public function closeShift(CloseShiftRequest $request): RedirectResponse
    {
        $shift = CashierShift::query()
            ->where('user_id', $request->user()->id)
            ->where('status', ShiftStatus::Open)
            ->latest('id')
            ->firstOrFail();

        $shift->close((int) $request->validated('closing_cash'));

        return redirect()->route('pos.shifts.report', $shift)->with('status', 'Shift kasir ditutup.');
    }

    public function checkout(CheckoutRequest $request, PointOfSaleService $pointOfSaleService): RedirectResponse
    {
        $validated = $request->validated();

        $sale = $pointOfSaleService->checkout($request->user(), [
            'items' => $validated['items'],
            'payments' => [
                [
                    'method' => $validated['payment_method'],
                    'amount' => (int) $validated['payment_amount'],
                    'reference' => $validated['payment_reference'] ?? null,
                ],
            ],
        ]);

        return redirect()
            ->route('transactions.receipt', $sale)
            ->with('status', "Transaksi {$sale->number} tersimpan.");
    }

    public function storeCashMovement(
        StoreCashMovementRequest $request,
        CashMovementService $cashMovementService
    ): RedirectResponse {
        if (! $request->user()->canApproveSensitiveActions()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();

        try {
            $cashMovementService->record($request->user(), [
                'type' => $validated['type'],
                'amount' => (int) $validated['amount'],
                'category' => $validated['category'] ?? null,
                'description' => $validated['description'],
            ]);

            return redirect()->route('pos.index')->with('status', 'Kas keluar atau setoran berhasil dicatat.');
        } catch (AuthorizationException) {
            return redirect()
                ->route('pos.index')
                ->with('status', 'Permintaan kas dikirim untuk approval admin/supervisor.');
        }
    }

    public function receipt(Request $request, SaleTransaction $saleTransaction): View
    {
        $this->authorizeTransactionAccess($request, $saleTransaction);

        return view('pos.receipt', [
            'sale' => $saleTransaction->load(['cashier', 'items', 'payments']),
        ]);
    }

    public function shiftReport(Request $request, CashierShift $cashierShift): View
    {
        $this->authorizeShiftAccess($request, $cashierShift);

        return view('pos.shift-report', [
            'shift' => $cashierShift->load(['user', 'saleTransactions.payments', 'cashMovements']),
            'summary' => $cashierShift->summary(),
        ]);
    }

    public function void(
        VoidTransactionRequest $request,
        SaleTransaction $saleTransaction,
        PointOfSaleService $pointOfSaleService
    ): RedirectResponse {
        try {
            $pointOfSaleService->void($saleTransaction, $request->user(), $request->validated('reason'));

            return redirect()->route('pos.index')->with('status', 'Transaksi berhasil di-void.');
        } catch (AuthorizationException) {
            return redirect()
                ->route('pos.index')
                ->with('status', 'Permintaan void dikirim untuk approval admin/supervisor.');
        }
    }

    public function refund(
        RefundTransactionRequest $request,
        SaleTransaction $saleTransaction,
        PointOfSaleService $pointOfSaleService
    ): RedirectResponse {
        $this->authorizeTransactionAccess($request, $saleTransaction);

        try {
            $pointOfSaleService->refund($saleTransaction, $request->user(), $request->validated('reason'));

            return redirect()->route('pos.index')->with('status', 'Transaksi berhasil direfund.');
        } catch (AuthorizationException) {
            return redirect()
                ->route('pos.index')
                ->with('status', 'Permintaan refund dikirim untuk approval admin/supervisor.');
        }
    }

    public function partialRefund(
        PartialRefundRequest $request,
        SaleTransaction $saleTransaction,
        PointOfSaleService $pointOfSaleService
    ): RedirectResponse {
        $this->authorizeTransactionAccess($request, $saleTransaction);

        try {
            $pointOfSaleService->partialRefund($saleTransaction, $request->user(), $request->validated());

            return redirect()
                ->route('transactions.receipt', $saleTransaction)
                ->with('status', 'Retur item berhasil dicatat.');
        } catch (AuthorizationException) {
            return redirect()
                ->route('transactions.receipt', $saleTransaction)
                ->with('status', 'Permintaan retur item dikirim untuk approval admin/supervisor.');
        }
    }

    private function authorizeTransactionAccess(Request $request, SaleTransaction $saleTransaction): void
    {
        $user = $request->user();

        if ($saleTransaction->cashier_id === $user->id || $user->canApproveSensitiveActions()) {
            return;
        }

        abort(Response::HTTP_FORBIDDEN);
    }

    private function authorizeShiftAccess(Request $request, CashierShift $cashierShift): void
    {
        $user = $request->user();

        if ($cashierShift->user_id === $user->id || $user->canApproveSensitiveActions()) {
            return;
        }

        abort(Response::HTTP_FORBIDDEN);
    }

    private function auditDraft(string $event, Request $request, HeldCart $heldCart): void
    {
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'auditable_type' => $heldCart::class,
            'auditable_id' => $heldCart->id,
            'event' => $event,
            'properties' => [
                'name' => $heldCart->name,
                'total' => $heldCart->total,
            ],
        ]);
    }
}
