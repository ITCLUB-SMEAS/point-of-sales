<?php

namespace App\Filament\Resources\ApprovalRequests\Pages;

use App\Filament\Resources\ApprovalRequests\ApprovalRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageApprovalRequests extends ManageRecords
{
    protected static string $resource = ApprovalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
