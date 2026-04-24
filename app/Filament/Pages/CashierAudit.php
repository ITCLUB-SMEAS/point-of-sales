<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\CashierAuditReport;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class CashierAudit extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Audit Kasir';

    protected static ?string $title = 'Audit Detail per Kasir';

    protected static ?string $slug = 'cashier-audit';

    protected string $view = 'filament.pages.cashier-audit';

    public string $date;

    public ?int $cashier = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->canApproveSensitiveActions() ?? false;
    }

    public function mount(): void
    {
        $this->date = request()->query('date', today()->toDateString());
        $cashierId = request()->integer('cashier');

        $this->cashier = $cashierId > 0
            ? $cashierId
            : $this->cashiers()->first()?->id;
    }

    /**
     * @return Collection<int, User>
     */
    public function cashiers(): Collection
    {
        return User::query()
            ->where('role', UserRole::Cashier)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function report(): ?array
    {
        $cashier = User::query()
            ->where('role', UserRole::Cashier)
            ->find($this->cashier);

        if (! $cashier instanceof User) {
            return null;
        }

        return app(CashierAuditReport::class)->forCashier($cashier, $this->date);
    }
}
