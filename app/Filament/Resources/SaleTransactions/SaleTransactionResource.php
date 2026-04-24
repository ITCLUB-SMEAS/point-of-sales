<?php

namespace App\Filament\Resources\SaleTransactions;

use App\Enums\TransactionStatus;
use App\Filament\Resources\SaleTransactions\Pages\ManageSaleTransactions;
use App\Models\SaleTransaction;
use App\Services\PointOfSaleService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SaleTransactionResource extends Resource
{
    protected static ?string $model = SaleTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Transaksi';

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('number'),
                TextEntry::make('cashier.name')->label('Kasir'),
                TextEntry::make('status')->badge(),
                TextEntry::make('total')->formatStateUsing(fn (int $state): string => 'Rp'.number_format($state, 0, ',', '.')),
                TextEntry::make('refund_reason')->label('Alasan Refund')->placeholder('-'),
                TextEntry::make('refunded_at')->label('Waktu Refund')->dateTime('d M Y H:i')->placeholder('-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                TextColumn::make('number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cashier.name')
                    ->label('Kasir')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (int $state): string => 'Rp'.number_format($state, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('refunded_at')
                    ->label('Refund')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('refund')
                    ->label('Refund')
                    ->color('warning')
                    ->icon(Heroicon::ArrowUturnLeft)
                    ->modalHeading(fn (SaleTransaction $record): string => "Refund {$record->number}")
                    ->schema([
                        Textarea::make('reason')
                            ->label('Alasan refund')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->visible(fn (SaleTransaction $record): bool => $record->status === TransactionStatus::Completed && (auth()->user()?->canApproveSensitiveActions() ?? false))
                    ->requiresConfirmation()
                    ->action(function (array $data, SaleTransaction $record): void {
                        app(PointOfSaleService::class)->refund($record, auth()->user(), $data['reason']);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSaleTransactions::route('/'),
        ];
    }
}
