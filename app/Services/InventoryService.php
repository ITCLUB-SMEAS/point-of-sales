<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function stockIn(Product $product, User $user, int $quantity, ?string $notes = null): InventoryMovement
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Jumlah stok masuk minimal 1.');
        }

        return $this->moveStock(
            product: $product,
            user: $user,
            type: InventoryMovementType::StockIn,
            delta: $quantity,
            notes: $notes,
            event: 'inventory.stock_in',
        );
    }

    public function adjust(Product $product, User $user, int $countedQuantity, ?string $notes = null): InventoryMovement
    {
        if ($countedQuantity < 0) {
            throw new InvalidArgumentException('Stok fisik tidak boleh negatif.');
        }

        return DB::transaction(function () use ($product, $user, $countedQuantity, $notes): InventoryMovement {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $this->ensureStockTracked($lockedProduct);

            $delta = $countedQuantity - $lockedProduct->stock_quantity;

            $lockedProduct->update(['stock_quantity' => $countedQuantity]);

            $movement = InventoryMovement::query()->create([
                'product_id' => $lockedProduct->id,
                'user_id' => $user->id,
                'type' => InventoryMovementType::Adjustment,
                'quantity' => $delta,
                'stock_after' => $countedQuantity,
                'notes' => $notes,
            ]);

            $this->audit('inventory.adjusted', $user, $lockedProduct, [
                'delta' => $delta,
                'stock_after' => $countedQuantity,
                'notes' => $notes,
            ]);

            return $movement;
        });
    }

    private function moveStock(
        Product $product,
        User $user,
        InventoryMovementType $type,
        int $delta,
        ?string $notes,
        string $event
    ): InventoryMovement {
        return DB::transaction(function () use ($product, $user, $type, $delta, $notes, $event): InventoryMovement {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $this->ensureStockTracked($lockedProduct);

            $stockAfter = $lockedProduct->stock_quantity + $delta;

            if ($stockAfter < 0) {
                throw new InvalidArgumentException('Stok tidak boleh negatif.');
            }

            $lockedProduct->update(['stock_quantity' => $stockAfter]);

            $movement = InventoryMovement::query()->create([
                'product_id' => $lockedProduct->id,
                'user_id' => $user->id,
                'type' => $type,
                'quantity' => $delta,
                'stock_after' => $stockAfter,
                'notes' => $notes,
            ]);

            $this->audit($event, $user, $lockedProduct, [
                'delta' => $delta,
                'stock_after' => $stockAfter,
                'notes' => $notes,
            ]);

            return $movement;
        });
    }

    private function ensureStockTracked(Product $product): void
    {
        if (! $product->is_stock_tracked) {
            throw new InvalidArgumentException('Produk ini tidak memakai pelacakan stok.');
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function audit(string $event, User $user, Product $product, array $properties): void
    {
        AuditLog::query()->create([
            'user_id' => $user->id,
            'auditable_type' => $product::class,
            'auditable_id' => $product->id,
            'event' => $event,
            'properties' => $properties,
        ]);
    }
}
