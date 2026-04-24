<?php

namespace App\Enums;

enum CashMovementType: string
{
    case Expense = 'expense';
    case Deposit = 'deposit';
}
