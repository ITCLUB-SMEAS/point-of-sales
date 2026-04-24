<?php

namespace App\Filament\Resources\CashierShifts\Pages;

use App\Filament\Resources\CashierShifts\CashierShiftResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCashierShifts extends ManageRecords
{
    protected static string $resource = CashierShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
