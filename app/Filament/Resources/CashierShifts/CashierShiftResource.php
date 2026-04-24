<?php

namespace App\Filament\Resources\CashierShifts;

use App\Filament\Resources\CashierShifts\Pages\ManageCashierShifts;
use App\Models\CashierShift;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CashierShiftResource extends Resource
{
    protected static ?string $model = CashierShift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Transaksi';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')->label('Kasir'),
                TextEntry::make('status')->badge(),
                TextEntry::make('opening_cash')->formatStateUsing(fn (int $state): string => 'Rp'.number_format($state, 0, ',', '.')),
                TextEntry::make('cash_variance')->formatStateUsing(fn (?int $state): string => $state === null ? '-' : 'Rp'.number_format($state, 0, ',', '.')),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Kasir')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('opening_cash')
                    ->label('Modal')
                    ->formatStateUsing(fn (int $state): string => 'Rp'.number_format($state, 0, ',', '.')),
                TextColumn::make('cash_variance')
                    ->label('Selisih')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '-' : 'Rp'.number_format($state, 0, ',', '.')),
                TextColumn::make('opened_at')
                    ->label('Dibuka')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('recap')
                    ->label('Rekap')
                    ->url(fn (CashierShift $record): string => url('/admin/shift-recap?shift='.$record->id)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCashierShifts::route('/'),
        ];
    }
}
