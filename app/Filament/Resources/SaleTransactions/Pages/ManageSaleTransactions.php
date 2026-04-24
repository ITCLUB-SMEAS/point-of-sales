<?php

namespace App\Filament\Resources\SaleTransactions\Pages;

use App\Filament\Resources\SaleTransactions\SaleTransactionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSaleTransactions extends ManageRecords
{
    protected static string $resource = SaleTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
