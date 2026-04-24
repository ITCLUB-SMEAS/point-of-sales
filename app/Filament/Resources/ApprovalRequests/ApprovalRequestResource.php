<?php

namespace App\Filament\Resources\ApprovalRequests;

use App\Enums\ApprovalStatus;
use App\Filament\Resources\ApprovalRequests\Pages\ManageApprovalRequests;
use App\Models\ApprovalRequest;
use App\Services\PointOfSaleService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApprovalRequestResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Kontrol';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('action')->label('Aksi')->badge(),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('requester.name')->label('Pemohon'),
                TextEntry::make('reason')->label('Alasan'),
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
                TextColumn::make('action')
                    ->label('Aksi')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('requester.name')
                    ->label('Pemohon')
                    ->searchable(),
                TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(40),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->visible(fn (ApprovalRequest $record): bool => $record->status === ApprovalStatus::Pending)
                    ->requiresConfirmation()
                    ->action(fn (ApprovalRequest $record): ApprovalRequest => app(PointOfSaleService::class)->approve($record, auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageApprovalRequests::route('/'),
        ];
    }
}
