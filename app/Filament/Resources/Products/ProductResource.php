<?php

namespace App\Filament\Resources\Products;

use App\Enums\ProductType;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Product;
use App\Services\InventoryService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Layanan & Barang';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')
                    ->label('SKU')
                    ->maxLength(255),
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Tipe')
                    ->options([
                        ProductType::Service->value => 'Layanan',
                        ProductType::Stock->value => 'Barang stok',
                    ])
                    ->required(),
                TextInput::make('unit')
                    ->label('Satuan')
                    ->required()
                    ->maxLength(50),
                TextInput::make('price')
                    ->label('Harga jual')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('cost')
                    ->label('Harga modal')
                    ->numeric()
                    ->minValue(0),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
                Toggle::make('is_stock_tracked')
                    ->label('Pantau stok'),
                TextInput::make('stock_quantity')
                    ->label('Stok')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('minimum_stock')
                    ->label('Stok minimum')
                    ->numeric()
                    ->required()
                    ->minValue(0),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('sku')->label('SKU'),
                TextEntry::make('name')->label('Nama'),
                TextEntry::make('type')->label('Tipe')->badge(),
                TextEntry::make('price')
                    ->label('Harga jual')
                    ->formatStateUsing(fn (int $state): string => 'Rp'.number_format($state, 0, ',', '.')),
                TextEntry::make('stock_quantity')->label('Stok'),
                TextEntry::make('minimum_stock')->label('Stok minimum'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge(),
                TextColumn::make('price')
                    ->label('Harga')
                    ->formatStateUsing(fn (int $state): string => 'Rp'.number_format($state, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('stock_quantity')
                    ->label('Stok')
                    ->sortable(),
                TextColumn::make('minimum_stock')
                    ->label('Min.')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('stockIn')
                    ->label('Stok Masuk')
                    ->modalHeading(fn (Product $record): string => "Stok Masuk {$record->name}")
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Jumlah masuk')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->maxLength(500),
                    ])
                    ->visible(fn (Product $record): bool => $record->is_stock_tracked)
                    ->action(function (array $data, Product $record): void {
                        app(InventoryService::class)->stockIn(
                            product: $record,
                            user: auth()->user(),
                            quantity: (int) $data['quantity'],
                            notes: $data['notes'] ?? null,
                        );
                    }),
                Action::make('adjustStock')
                    ->label('Adjustment')
                    ->modalHeading(fn (Product $record): string => "Adjustment {$record->name}")
                    ->fillForm(fn (Product $record): array => [
                        'counted_quantity' => $record->stock_quantity,
                    ])
                    ->schema([
                        TextInput::make('counted_quantity')
                            ->label('Stok fisik')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->maxLength(500),
                    ])
                    ->visible(fn (Product $record): bool => $record->is_stock_tracked)
                    ->requiresConfirmation()
                    ->action(function (array $data, Product $record): void {
                        app(InventoryService::class)->adjust(
                            product: $record,
                            user: auth()->user(),
                            countedQuantity: (int) $data['counted_quantity'],
                            notes: $data['notes'] ?? null,
                        );
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProducts::route('/'),
        ];
    }
}
