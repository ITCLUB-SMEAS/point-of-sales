<?php

namespace App\Filament\Resources\CashMovements;

use App\Filament\Resources\CashMovements\Pages\ManageCashMovements;
use App\Models\CashMovement;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CashMovementResource extends Resource
{
    protected static ?string $model = CashMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Kas';

    protected static ?string $navigationLabel = 'Kas Keluar & Setoran';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('type')->label('Tipe')->badge(),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('user.name')->label('Dicatat oleh'),
                TextEntry::make('amount')->label('Nominal')->formatStateUsing(fn (int $state): string => 'Rp'.number_format($state, 0, ',', '.')),
                TextEntry::make('category')->label('Kategori')->placeholder('-'),
                TextEntry::make('description')->label('Catatan')->placeholder('-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn (int $state): string => 'Rp'.number_format($state, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCashMovements::route('/'),
        ];
    }
}
