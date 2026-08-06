<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\StarkbankTransfers\Tables;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use He4rt\FakeStarkbank\Transfer\Actions\ForceTransferStatus;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;

class StarkbankTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('tax_id')
                    ->label(__('panel-admin::fake-starkbank.transfers.columns.tax_id')),
                TextColumn::make('external_id')
                    ->label(__('panel-admin::fake-starkbank.transfers.columns.external_id'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('destined_status')
                    ->label(__('panel-admin::fake-starkbank.transfers.columns.destined_status'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('failure_reason')
                    ->label(__('panel-admin::fake-starkbank.transfers.columns.failure_reason'))
                    ->placeholder('—')
                    ->limit(30),
                IconColumn::make('held')
                    ->boolean()
                    ->label(__('panel-admin::fake-starkbank.transfers.columns.held')),
                TextColumn::make('created_at')
                    ->dateTime(timezone: config('app.display_timezone'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(TransferStatus::class),
                TernaryFilter::make('held'),
            ])
            ->recordActions([
                self::forceStatusAction(),
            ]);
    }

    private static function forceStatusAction(): Action
    {
        return Action::make('forceStatus')
            ->label(__('panel-admin::fake-starkbank.transfers.actions.force_status'))
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('danger')
            ->schema([
                Select::make('status')
                    ->label(__('panel-admin::fake-starkbank.transfers.actions.status_field'))
                    ->options(TransferStatus::class)
                    ->required(),
            ])
            ->action(function (array $data, Transfer $record): void {
                // `options(Enum::class)` hidrata o estado como instância do
                // enum, não como a string do banco — um `from()` aqui receberia
                // o próprio enum e estouraria.
                /** @var array{status: TransferStatus} $data */
                resolve(ForceTransferStatus::class)->handle($record, $data['status']);

                Notification::make()->title(__('panel-admin::fake-starkbank.transfers.actions.force_status_notification'))->success()->send();
            });
    }
}
