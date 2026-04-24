<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sale_transaction_id',
    'product_id',
    'product_name',
    'quantity',
    'unit_price',
    'unit_cost',
    'subtotal',
    'source_note',
    'is_stock_tracked',
])]
class SaleTransactionItem extends Model
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

    public function saleTransaction(): BelongsTo
    {
        return $this->belongsTo(SaleTransaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function refundItems(): HasMany
    {
        return $this->hasMany(SaleRefundItem::class);
    }
}
