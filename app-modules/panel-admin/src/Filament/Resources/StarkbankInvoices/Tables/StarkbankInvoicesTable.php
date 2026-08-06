<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\StarkbankInvoices\Tables;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use He4rt\FakeStarkbank\Invoice\Actions\ForceInvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Actions\SetInvoiceFrozen;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;

class StarkbankInvoicesTable
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
                    ->label(__('panel-admin::fake-starkbank.invoices.columns.tax_id')),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('destined_status')
                    ->label(__('panel-admin::fake-starkbank.invoices.columns.destined_status'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('extra_advance_seconds')
                    ->label(__('panel-admin::fake-starkbank.invoices.columns.extra_advance_seconds'))
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('frozen')
                    ->boolean()
                    ->label(__('panel-admin::fake-starkbank.invoices.columns.frozen')),
                TextColumn::make('created_at')
                    ->dateTime(timezone: config('app.display_timezone'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(InvoiceStatus::class),
                TernaryFilter::make('frozen'),
            ])
            ->recordActions([
                self::forceStatusAction(),
                self::toggleFrozenAction(),
            ]);
    }

    private static function forceStatusAction(): Action
    {
        return Action::make('forceStatus')
            ->label(__('panel-admin::fake-starkbank.invoices.actions.force_status'))
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('danger')
            ->schema([
                Select::make('status')
                    ->label(__('panel-admin::fake-starkbank.invoices.actions.status_field'))
                    ->options(InvoiceStatus::class)
                    ->required(),
            ])
            ->action(function (array $data, Invoice $record): void {
                // `options(Enum::class)` hidrata o estado como instância do
                // enum, não como a string do banco — um `from()` aqui receberia
                // o próprio enum e estouraria.
                /** @var array{status: InvoiceStatus} $data */
                resolve(ForceInvoiceStatus::class)->handle($record, $data['status']);

                Notification::make()->title(__('panel-admin::fake-starkbank.invoices.actions.force_status_notification'))->success()->send();
            });
    }

    private static function toggleFrozenAction(): Action
    {
        return Action::make('toggleFrozen')
            ->label(fn (Invoice $record): string => __('panel-admin::fake-starkbank.invoices.actions.'.($record->frozen ? 'unfreeze' : 'freeze')))
            ->icon(fn (Invoice $record): Heroicon => $record->frozen ? Heroicon::OutlinedPlay : Heroicon::OutlinedPause)
            ->color('gray')
            ->action(function (Invoice $record): void {
                resolve(SetInvoiceFrozen::class)->handle($record, !$record->frozen);

                Notification::make()->title(__('panel-admin::fake-starkbank.invoices.actions.frozen_notification'))->success()->send();
            });
    }
}
