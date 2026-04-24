<?php

namespace App\Enums;

enum ApprovalAction: string
{
    case VoidTransaction = 'void_transaction';
    case RefundTransaction = 'refund_transaction';
    case PartialRefund = 'partial_refund';
    case CashMovement = 'cash_movement';
    case ManualDiscount = 'manual_discount';
    case ManualPriceChange = 'manual_price_change';
    case DeleteTransaction = 'delete_transaction';
}
