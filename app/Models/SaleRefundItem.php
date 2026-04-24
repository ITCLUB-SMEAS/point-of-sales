<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sale_refund_id',
    'sale_transaction_item_id',
    'product_id',
    'product_name',
    'quantity',
    'unit_price',
    'unit_cost',
    'subtotal',
    'is_stock_tracked',
])]
class SaleRefundItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'unit_cost' => 'integer',
            'subtotal' => 'integer',
            'is_stock_tracked' => 'boolean',
        ];
    }

    public function saleRefund(): BelongsTo
    {
        return $this->belongsTo(SaleRefund::class);
    }

    public function saleTransactionItem(): BelongsTo
    {
        return $this->belongsTo(SaleTransactionItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
