<?php

namespace App\Filament\Resources\ServicePackages;

use App\Filament\Resources\ServicePackages\Pages\ManageServicePackages;
use App\Models\ServicePackage;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServicePackageResource extends Resource
{
    protected static ?string $model = ServicePackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Paket Layanan';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama paket')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->maxLength(500),
                TextInput::make('price')
                    ->label('Harga paket')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
                Repeater::make('items')
                    ->label('Isi paket')
                    ->relationship()
                    ->schema([
                        Select::make('product_id')
                            ->label('Layanan/barang')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('quantity')
                            ->label('Qty')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                        TextInput::make('unit_price')
                            ->label('Harga item')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                    ])
                    ->columns(3)
                    ->defaultItems(1),
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
                TextColumn::make('price')
                    ->label('Harga')
                    ->formatStateUsing(fn (int $state): string => 'Rp'.number_format($state, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Isi')
                    ->counts('items')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->recordActions([
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
            'index' => ManageServicePackages::route('/'),
        ];
    }
}
