<?php

namespace App\Filament\Resources\SaleRefunds;

use App\Filament\Resources\SaleRefunds\Pages\ManageSaleRefunds;
use App\Models\SaleRefund;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SaleRefundResource extends Resource
{
    protected static ?string $model = SaleRefund::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static string|\UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Retur Item';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('saleTransaction.number')->label('Transaksi'),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('requester.name')->label('Pemohon'),
                TextEntry::make('approver.name')->label('Approver')->placeholder('-'),
                TextEntry::make('amount_total')->label('Nominal')->formatStateUsing(fn (int $state): string => 'Rp'.number_format($state, 0, ',', '.')),
                TextEntry::make('reason')->label('Alasan'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('saleTransaction.number')
                    ->label('Transaksi')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('requester.name')
                    ->label('Pemohon')
                    ->searchable(),
                TextColumn::make('amount_total')
                    ->label('Nominal')
                    ->formatStateUsing(fn (int $state): string => 'Rp'.number_format($state, 0, ',', '.'))
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSaleRefunds::route('/'),
        ];
    }
}
