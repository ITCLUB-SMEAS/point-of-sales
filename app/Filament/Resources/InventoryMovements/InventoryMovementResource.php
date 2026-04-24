<?php

namespace App\Filament\Resources\InventoryMovements;

use App\Filament\Resources\InventoryMovements\Pages\ManageInventoryMovements;
use App\Models\InventoryMovement;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('product.name')->label('Produk'),
                TextEntry::make('type')->badge(),
                TextEntry::make('quantity'),
                TextEntry::make('stock_after'),
                TextEntry::make('notes'),
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
                TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->sortable(),
                TextColumn::make('stock_after')
                    ->label('Stok akhir')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInventoryMovements::route('/'),
        ];
    }
}
