<?php

namespace App\Filament\Pages;

use App\Services\ReorderList as ReorderListService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ReorderList extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    protected static ?string $navigationLabel = 'Stok Menipis';

    protected static ?string $title = 'Stok Menipis';

    protected string $view = 'filament.pages.reorder-list';

    public static function canAccess(): bool
    {
        return auth()->user()?->canApproveSensitiveActions() ?? false;
    }

    /**
     * @return array<int, array{id: int, name: string, sku: ?string, unit: string, stock_quantity: int, minimum_stock: int, shortage: int, recommended_order_quantity: int}>
     */
    public function items(): array
    {
        return app(ReorderListService::class)->items();
    }
}
