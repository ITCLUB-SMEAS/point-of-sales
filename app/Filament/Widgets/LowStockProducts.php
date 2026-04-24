<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ReorderList as ReorderListPage;
use App\Services\ReorderList;
use Filament\Widgets\Widget;

class LowStockProducts extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.low-stock-products';

    public static function canView(): bool
    {
        return auth()->user()?->canApproveSensitiveActions() ?? false;
    }

    /**
     * @return array<int, array{id: int, name: string, sku: ?string, unit: string, stock_quantity: int, minimum_stock: int, shortage: int, recommended_order_quantity: int}>
     */
    public function items(): array
    {
        return app(ReorderList::class)->items(5);
    }

    public function reorderListUrl(): string
    {
        return ReorderListPage::getUrl();
    }
}
