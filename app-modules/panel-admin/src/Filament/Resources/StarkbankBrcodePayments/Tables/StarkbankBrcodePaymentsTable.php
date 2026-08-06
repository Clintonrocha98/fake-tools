<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\StarkbankBrcodePayments\Tables;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use He4rt\FakeStarkbank\Brcode\Actions\ForceBrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;

class StarkbankBrcodePaymentsTable
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
                TextColumn::make('tax_id')
                    ->label(__('panel-admin::fake-starkbank.brcode_payments.columns.tax_id')),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('destined_status')
                    ->label(__('panel-admin::fake-starkbank.brcode_payments.columns.destined_status'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('failure_reason')
                    ->label(__('panel-admin::fake-starkbank.brcode_payments.columns.failure_reason'))
                    ->placeholder('—')
                    ->limit(30),
                IconColumn::make('held')
                    ->boolean()
                    ->label(__('panel-admin::fake-starkbank.brcode_payments.columns.held')),
                TextColumn::make('brcode')
                    ->limit(24)
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime(timezone: config('app.display_timezone'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BrcodePaymentStatus::class),
                TernaryFilter::make('held'),
            ])
            ->recordActions([
                self::forceStatusAction(),
            ]);
    }

    private static function forceStatusAction(): Action
    {
        return Action::make('forceStatus')
            ->label(__('panel-admin::fake-starkbank.brcode_payments.actions.force_status'))
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('danger')
            ->schema([
                Select::make('status')
                    ->label(__('panel-admin::fake-starkbank.brcode_payments.actions.status_field'))
                    ->options(BrcodePaymentStatus::class)
                    ->required(),
            ])
            ->action(function (array $data, BrcodePayment $record): void {
                // `options(Enum::class)` hidrata o estado como instância do
                // enum, não como a string do banco — um `from()` aqui receberia
                // o próprio enum e estouraria.
                /** @var array{status: BrcodePaymentStatus} $data */
                resolve(ForceBrcodePaymentStatus::class)->handle($record, $data['status']);

                Notification::make()->title(__('panel-admin::fake-starkbank.brcode_payments.actions.force_status_notification'))->success()->send();
            });
    }
}
