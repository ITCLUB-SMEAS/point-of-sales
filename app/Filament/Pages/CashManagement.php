<?php

namespace App\Filament\Pages;

use App\Models\CashMovement;
use App\Models\CashierShift;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class CashManagement extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Kas';

    protected static ?string $navigationLabel = 'Manajemen Kas';

    protected static ?string $title = 'Manajemen Kas';

    protected static ?string $slug = 'cash-management';

    protected string $view = 'filament.pages.cash-management';

    public static function canAccess(): bool
    {
        return auth()->user()?->canApproveSensitiveActions() ?? false;
    }

    /**
     * @return Collection<int, CashierShift>
     */
    public function shifts(): Collection
    {
        return CashierShift::query()
            ->with('user')
            ->latest('id')
            ->limit(40)
            ->get();
    }

    /**
     * @return Collection<int, CashMovement>
     */
    public function recentMovements(): Collection
    {
        return CashMovement::query()
            ->with(['user', 'cashierShift.user'])
            ->latest('id')
            ->limit(15)
            ->get();
    }
}
