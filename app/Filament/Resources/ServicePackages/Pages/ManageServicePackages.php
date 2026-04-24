<?php

namespace App\Filament\Resources\ServicePackages\Pages;

use App\Filament\Resources\ServicePackages\ServicePackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageServicePackages extends ManageRecords
{
    protected static string $resource = ServicePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
