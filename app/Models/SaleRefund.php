<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Database\Factories\SaleRefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sale_transaction_id',
    'requested_by',
    'approved_by',
    'status',
    'amount_total',
    'reason',
    'decided_at',
])]
class SaleRefund extends Model
{
    /** @use HasFactory<SaleRefundFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'amount_total' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function saleTransaction(): BelongsTo
    {
        return $this->belongsTo(SaleTransaction::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleRefundItem::class);
    }
}
