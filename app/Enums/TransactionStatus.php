<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Completed = 'completed';
    case PartiallyRefunded = 'partially_refunded';
    case Voided = 'voided';
    case Refunded = 'refunded';
}
