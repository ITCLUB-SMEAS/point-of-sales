<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalStatus;
use App\Enums\InventoryMovementType;
use App\Enums\ShiftStatus;
use App\Enums\TransactionStatus;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\CashierShift;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\SaleRefund;
use App\Models\SaleRefundItem;
use App\Models\SaleTransaction;
use App\Models\SaleTransactionItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PointOfSaleService
{
    /**
     * @param  array{items: array<int, array{product_id: int, quantity: int, unit_price?: int, source_note?: string|null}>, payments: array<int, array{method: mixed, amount: int, reference?: string|null}>, discount_total?: int}  $payload
     */
    public function checkout(User $cashier, array $payload): SaleTransaction
    {
        return DB::transaction(function () use ($cashier, $payload): SaleTransaction {
            $shift = CashierShift::query()
                ->where('user_id', $cashier->id)
                ->where('status', ShiftStatus::Open)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $shift instanceof CashierShift) {
                throw new InvalidArgumentException('Kasir belum membuka shift.');
            }

            $items = Arr::get($payload, 'items', []);
            $payments = Arr::get($payload, 'payments', []);

            if ($items === [] || $payments === []) {
                throw new InvalidArgumentException('Transaksi membutuhkan item dan pembayaran.');
            }

            $subtotal = 0;
            $preparedItems = [];

            foreach ($items as $item) {
                $quantity = (int) $item['quantity'];

                if ($quantity < 1) {
                    throw new InvalidArgumentException('Jumlah item minimal 1.');
                }

                $product = Product::query()->whereKey($item['product_id'])->lockForUpdate()->firstOrFail();
                $unitPrice = (int) ($item['unit_price'] ?? $product->price);
                $lineSubtotal = $unitPrice * $quantity;
                $subtotal += $lineSubtotal;

                if ($product->is_stock_tracked && $product->stock_quantity < $quantity) {
                    throw new InvalidArgumentException("Stok {$product->name} tidak cukup.");
                }

                $preparedItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_cost' => (int) ($product->cost ?? 0),
                    'subtotal' => $lineSubtotal,
                    'source_note' => $item['source_note'] ?? null,
                ];
            }

            $discountTotal = (int) Arr::get($payload, 'discount_total', 0);
            $total = max(0, $subtotal - $discountTotal);
            $paidTotal = collect($payments)->sum(fn (array $payment): int => (int) $payment['amount']);

            if ($paidTotal < $total) {
                throw new InvalidArgumentException('Pembayaran kurang dari total transaksi.');
            }

            $sale = SaleTransaction::query()->create([
                'number' => $this->nextTransactionNumber(),
                'cashier_shift_id' => $shift->id,
                'cashier_id' => $cashier->id,
                'status' => TransactionStatus::Completed,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'total' => $total,
                'paid_total' => $paidTotal,
                'change_total' => $paidTotal - $total,
            ]);

            foreach ($preparedItems as $preparedItem) {
                /** @var Product $product */
                $product = $preparedItem['product'];

                $sale->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $preparedItem['quantity'],
                    'unit_price' => $preparedItem['unit_price'],
                    'unit_cost' => $preparedItem['unit_cost'],
                    'subtotal' => $preparedItem['subtotal'],
                    'source_note' => $preparedItem['source_note'],
                    'is_stock_tracked' => $product->is_stock_tracked,
                ]);

                if ($product->is_stock_tracked) {
                    $product->decrement('stock_quantity', $preparedItem['quantity']);
                    $product->refresh();

                    InventoryMovement::query()->create([
                        'product_id' => $product->id,
                        'user_id' => $cashier->id,
                        'sale_transaction_id' => $sale->id,
                        'type' => InventoryMovementType::Sale,
                        'quantity' => -$preparedItem['quantity'],
                        'stock_after' => $product->stock_quantity,
                        'notes' => 'Penjualan '.$sale->number,
                    ]);
                }
            }

            foreach ($payments as $payment) {
                $sale->payments()->create([
                    'method' => $payment['method'],
                    'amount' => (int) $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                ]);
            }

            $this->audit('sale.completed', $cashier, $sale, [
                'total' => $total,
                'paid_total' => $paidTotal,
            ]);

            return $sale->load(['items', 'payments']);
        });
    }

    public function void(SaleTransaction $saleTransaction, User $requestedBy, string $reason): void
    {
        if (! $requestedBy->canApproveSensitiveActions()) {
            ApprovalRequest::query()->firstOrCreate([
                'approvable_type' => $saleTransaction->getMorphClass(),
                'approvable_id' => $saleTransaction->id,
                'action' => ApprovalAction::VoidTransaction,
                'status' => ApprovalStatus::Pending,
            ], [
                'requested_by' => $requestedBy->id,
                'reason' => $reason,
            ]);

            throw new AuthorizationException('Void transaksi membutuhkan approval admin atau supervisor.');
        }

        DB::transaction(function () use ($saleTransaction, $requestedBy, $reason): void {
            $this->applyVoid($saleTransaction, $requestedBy, $reason);
        });
    }

    public function refund(SaleTransaction $saleTransaction, User $requestedBy, string $reason): void
    {
        if (! $requestedBy->canApproveSensitiveActions()) {
            ApprovalRequest::query()->firstOrCreate([
                'approvable_type' => $saleTransaction->getMorphClass(),
                'approvable_id' => $saleTransaction->id,
                'action' => ApprovalAction::RefundTransaction,
                'status' => ApprovalStatus::Pending,
            ], [
                'requested_by' => $requestedBy->id,
                'reason' => $reason,
            ]);

            throw new AuthorizationException('Refund transaksi membutuhkan approval admin atau supervisor.');
        }

        DB::transaction(function () use ($saleTransaction, $requestedBy, $reason): void {
            $this->applyRefund($saleTransaction, $requestedBy, $reason);
        });
    }

    /**
     * @param  array{reason: string, items: array<int, array{sale_transaction_item_id: int, quantity: int}>}  $payload
     */
    public function partialRefund(SaleTransaction $saleTransaction, User $requestedBy, array $payload): SaleRefund
    {
        $status = $requestedBy->canApproveSensitiveActions()
            ? ApprovalStatus::Approved
            : ApprovalStatus::Pending;

        $refund = DB::transaction(function () use ($saleTransaction, $requestedBy, $payload, $status): SaleRefund {
            $saleTransaction->refresh();

            if (! in_array($saleTransaction->status, [TransactionStatus::Completed, TransactionStatus::PartiallyRefunded], true)) {
                throw new InvalidArgumentException('Hanya transaksi selesai yang bisa diretur sebagian.');
            }

            $preparedItems = $this->preparePartialRefundItems($saleTransaction, $payload['items']);
            $amountTotal = collect($preparedItems)->sum(fn (array $item): int => $item['subtotal']);

            $refund = SaleRefund::query()->create([
                'sale_transaction_id' => $saleTransaction->id,
                'requested_by' => $requestedBy->id,
                'approved_by' => null,
                'status' => ApprovalStatus::Pending,
                'amount_total' => $amountTotal,
                'reason' => $payload['reason'],
            ]);

            foreach ($preparedItems as $preparedItem) {
                /** @var SaleTransactionItem $saleItem */
                $saleItem = $preparedItem['sale_item'];

                $refund->items()->create([
                    'sale_transaction_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'product_name' => $saleItem->product_name,
                    'quantity' => $preparedItem['quantity'],
                    'unit_price' => $saleItem->unit_price,
                    'unit_cost' => $saleItem->unit_cost,
                    'subtotal' => $preparedItem['subtotal'],
                    'is_stock_tracked' => $saleItem->is_stock_tracked,
                ]);
            }

            if ($status === ApprovalStatus::Pending) {
                ApprovalRequest::query()->create([
                    'requested_by' => $requestedBy->id,
                    'approvable_type' => $refund->getMorphClass(),
                    'approvable_id' => $refund->id,
                    'action' => ApprovalAction::PartialRefund,
                    'status' => ApprovalStatus::Pending,
                    'reason' => $refund->reason,
                ]);

                $this->audit('sale_refund.requested', $requestedBy, $refund, [
                    'sale_transaction_id' => $saleTransaction->id,
                    'amount_total' => $amountTotal,
                ]);

                return $refund;
            }

            $this->applyPartialRefund($refund, $requestedBy);

            return $refund->fresh(['items']);
        });

        if ($refund->status === ApprovalStatus::Pending) {
            throw new AuthorizationException('Retur item membutuhkan approval admin atau supervisor.');
        }

        return $refund;
    }

    public function approve(ApprovalRequest $approvalRequest, User $approvedBy): ApprovalRequest
    {
        if (! $approvedBy->canApproveSensitiveActions()) {
            throw new AuthorizationException('Hanya admin atau supervisor yang bisa menyetujui approval.');
        }

        return DB::transaction(function () use ($approvalRequest, $approvedBy): ApprovalRequest {
            $approvalRequest->update([
                'approved_by' => $approvedBy->id,
                'status' => ApprovalStatus::Approved,
                'decided_at' => now(),
            ]);

            if ($approvalRequest->action === ApprovalAction::VoidTransaction) {
                $saleTransaction = $approvalRequest->approvable;

                if ($saleTransaction instanceof SaleTransaction) {
                    $this->applyVoid($saleTransaction, $approvedBy, $approvalRequest->reason ?? 'Approved void');
                }
            }

            if ($approvalRequest->action === ApprovalAction::RefundTransaction) {
                $saleTransaction = $approvalRequest->approvable;

                if ($saleTransaction instanceof SaleTransaction) {
                    $this->applyRefund($saleTransaction, $approvedBy, $approvalRequest->reason ?? 'Approved refund');
                }
            }

            if ($approvalRequest->action === ApprovalAction::PartialRefund) {
                $saleRefund = $approvalRequest->approvable;

                if ($saleRefund instanceof SaleRefund) {
                    $this->applyPartialRefund($saleRefund, $approvedBy);
                }
            }

            if ($approvalRequest->action === ApprovalAction::CashMovement) {
                $cashMovement = $approvalRequest->approvable;

                if ($cashMovement instanceof CashMovement) {
                    app(CashMovementService::class)->approve($cashMovement, $approvedBy);
                }
            }

            $this->audit('approval.approved', $approvedBy, $approvalRequest, [
                'action' => $approvalRequest->action->value,
            ]);

            return $approvalRequest->fresh();
        });
    }

    private function applyVoid(SaleTransaction $saleTransaction, User $voidedBy, string $reason): void
    {
        $saleTransaction->refresh();

        if ($saleTransaction->status === TransactionStatus::Voided) {
            return;
        }

        $saleTransaction->loadMissing('items.product');

        foreach ($saleTransaction->items as $item) {
            if (! $item->is_stock_tracked) {
                continue;
            }

            $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
            $product->increment('stock_quantity', $item->quantity);
            $product->refresh();

            InventoryMovement::query()->create([
                'product_id' => $product->id,
                'user_id' => $voidedBy->id,
                'sale_transaction_id' => $saleTransaction->id,
                'type' => InventoryMovementType::VoidReturn,
                'quantity' => $item->quantity,
                'stock_after' => $product->stock_quantity,
                'notes' => 'Void '.$saleTransaction->number,
            ]);
        }

        $saleTransaction->update([
            'status' => TransactionStatus::Voided,
            'voided_by' => $voidedBy->id,
            'voided_at' => now(),
            'void_reason' => $reason,
        ]);

        $this->audit('sale.voided', $voidedBy, $saleTransaction, ['reason' => $reason]);
    }

    private function applyRefund(SaleTransaction $saleTransaction, User $refundedBy, string $reason): void
    {
        $saleTransaction->refresh();

        if ($saleTransaction->status === TransactionStatus::Refunded) {
            return;
        }

        if ($saleTransaction->status !== TransactionStatus::Completed) {
            throw new InvalidArgumentException('Hanya transaksi selesai yang bisa direfund.');
        }

        $saleTransaction->loadMissing('items.product');

        foreach ($saleTransaction->items as $item) {
            if (! $item->is_stock_tracked) {
                continue;
            }

            $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
            $product->increment('stock_quantity', $item->quantity);
            $product->refresh();

            InventoryMovement::query()->create([
                'product_id' => $product->id,
                'user_id' => $refundedBy->id,
                'sale_transaction_id' => $saleTransaction->id,
                'type' => InventoryMovementType::RefundReturn,
                'quantity' => $item->quantity,
                'stock_after' => $product->stock_quantity,
                'notes' => 'Refund '.$saleTransaction->number,
            ]);
        }

        $saleTransaction->update([
            'status' => TransactionStatus::Refunded,
            'refunded_by' => $refundedBy->id,
            'refunded_at' => now(),
            'refund_reason' => $reason,
        ]);

        $this->audit('sale.refunded', $refundedBy, $saleTransaction, ['reason' => $reason]);
    }

    /**
     * @param  array<int, array{sale_transaction_item_id: int, quantity: int}>  $items
     * @return array<int, array{sale_item: SaleTransactionItem, quantity: int, subtotal: int}>
     */
    private function preparePartialRefundItems(SaleTransaction $saleTransaction, array $items): array
    {
        if ($items === []) {
            throw new InvalidArgumentException('Retur membutuhkan minimal satu item.');
        }

        $preparedItems = [];

        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];

            if ($quantity < 1) {
                throw new InvalidArgumentException('Jumlah retur minimal 1.');
            }

            $saleItem = SaleTransactionItem::query()
                ->where('sale_transaction_id', $saleTransaction->id)
                ->whereKey($item['sale_transaction_item_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $remainingQuantity = $saleItem->quantity - $this->approvedRefundedQuantity($saleItem);

            if ($quantity > $remainingQuantity) {
                throw new InvalidArgumentException("Jumlah retur {$saleItem->product_name} melebihi sisa yang bisa diretur.");
            }

            $preparedItems[] = [
                'sale_item' => $saleItem,
                'quantity' => $quantity,
                'subtotal' => $quantity * $saleItem->unit_price,
            ];
        }

        return $preparedItems;
    }

    private function approvedRefundedQuantity(SaleTransactionItem $saleItem): int
    {
        return (int) SaleRefundItem::query()
            ->where('sale_transaction_item_id', $saleItem->id)
            ->whereHas('saleRefund', function ($query): void {
                $query->where('status', ApprovalStatus::Approved);
            })
            ->sum('quantity');
    }

    private function applyPartialRefund(SaleRefund $saleRefund, User $approvedBy): void
    {
        $saleRefund->refresh();

        if ($saleRefund->status === ApprovalStatus::Approved) {
            return;
        }

        $saleRefund->loadMissing(['saleTransaction.items', 'items']);

        foreach ($saleRefund->items as $refundItem) {
            if (! $refundItem->is_stock_tracked) {
                continue;
            }

            $product = Product::query()->whereKey($refundItem->product_id)->lockForUpdate()->firstOrFail();
            $product->increment('stock_quantity', $refundItem->quantity);
            $product->refresh();

            InventoryMovement::query()->create([
                'product_id' => $product->id,
                'user_id' => $approvedBy->id,
                'sale_transaction_id' => $saleRefund->sale_transaction_id,
                'type' => InventoryMovementType::RefundReturn,
                'quantity' => $refundItem->quantity,
                'stock_after' => $product->stock_quantity,
                'notes' => 'Retur item '.$saleRefund->saleTransaction->number,
            ]);
        }

        $saleRefund->update([
            'status' => ApprovalStatus::Approved,
            'approved_by' => $approvedBy->id,
            'decided_at' => now(),
        ]);

        $this->updateSaleStatusAfterPartialRefund($saleRefund->saleTransaction);
        $this->audit('sale_refund.approved', $approvedBy, $saleRefund, [
            'sale_transaction_id' => $saleRefund->sale_transaction_id,
            'amount_total' => $saleRefund->amount_total,
        ]);
    }

    private function updateSaleStatusAfterPartialRefund(SaleTransaction $saleTransaction): void
    {
        $totalQuantity = (int) $saleTransaction->items()->sum('quantity');
        $refundedQuantity = (int) SaleRefundItem::query()
            ->whereHas('saleRefund', function ($query) use ($saleTransaction): void {
                $query
                    ->where('sale_transaction_id', $saleTransaction->id)
                    ->where('status', ApprovalStatus::Approved);
            })
            ->sum('quantity');

        $saleTransaction->update([
            'status' => $refundedQuantity >= $totalQuantity
                ? TransactionStatus::Refunded
                : TransactionStatus::PartiallyRefunded,
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function audit(string $event, User $user, Model $auditable, array $properties = []): void
    {
        AuditLog::query()->create([
            'user_id' => $user->id,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->id,
            'event' => $event,
            'properties' => $properties,
        ]);
    }

    private function nextTransactionNumber(): string
    {
        return 'TRX-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5));
    }
}
