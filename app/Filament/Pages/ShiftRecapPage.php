<?php

namespace App\Filament\Pages;

use App\Models\CashierShift;
use App\Services\ShiftRecap;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class ShiftRecapPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Rekap Shift';

    protected static ?string $title = 'Rekap Shift';

    protected static ?string $slug = 'shift-recap';

    protected string $view = 'filament.pages.shift-recap';

    public ?int $shift = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->canApproveSensitiveActions() ?? false;
    }

    public function mount(): void
    {
        $shiftId = request()->integer('shift');
        $this->shift = $shiftId > 0
            ? $shiftId
            : $this->shifts()->first()?->id;
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
     * @return array<string, mixed>|null
     */
    public function report(): ?array
    {
        $shift = CashierShift::query()
            ->with('user')
            ->find($this->shift);

        if (! $shift instanceof CashierShift) {
            return null;
        }

        return app(ShiftRecap::class)->forShift($shift);
    }
}
