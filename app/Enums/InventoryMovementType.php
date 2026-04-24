<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Sale = 'sale';
    case VoidReturn = 'void_return';
    case RefundReturn = 'refund_return';
    case StockIn = 'stock_in';
    case Adjustment = 'adjustment';
}
