<?php

namespace App\Filament\Resources\CashMovements\Pages;

use App\Filament\Resources\CashMovements\CashMovementResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCashMovements extends ManageRecords
{
    protected static string $resource = CashMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
