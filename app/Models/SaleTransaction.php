<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'cashier_shift_id',
    'cashier_id',
    'status',
    'subtotal',
    'discount_total',
    'total',
    'paid_total',
    'change_total',
    'voided_by',
    'voided_at',
    'void_reason',
    'refunded_by',
    'refunded_at',
    'refund_reason',
])]
class SaleTransaction extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'total' => 'integer',
            'paid_total' => 'integer',
            'change_total' => 'integer',
            'voided_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function cashierShift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleTransactionItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(SaleRefund::class);
    }
}
