<?php

namespace App\Services;

use App\Models\Product;

class ReorderList
{
    /**
     * @return array<int, array{id: int, name: string, sku: ?string, unit: string, stock_quantity: int, minimum_stock: int, shortage: int, recommended_order_quantity: int}>
     */
    public function items(?int $limit = null): array
    {
        $query = Product::query()
            ->where('is_active', true)
            ->where('is_stock_tracked', true)
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->orderByRaw('(stock_quantity - minimum_stock) asc')
            ->orderBy('name');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query
            ->get(['id', 'name', 'sku', 'unit', 'stock_quantity', 'minimum_stock'])
            ->map(function (Product $product): array {
                $shortage = max($product->minimum_stock - $product->stock_quantity, 0);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit,
                    'stock_quantity' => $product->stock_quantity,
                    'minimum_stock' => $product->minimum_stock,
                    'shortage' => $shortage,
                    'recommended_order_quantity' => max($shortage, 1),
                ];
            })
            ->all();
    }
}
