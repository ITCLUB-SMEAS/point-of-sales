<?php

namespace App\Filament\Resources\SaleRefunds\Pages;

use App\Filament\Resources\SaleRefunds\SaleRefundResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSaleRefunds extends ManageRecords
{
    protected static string $resource = SaleRefundResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
