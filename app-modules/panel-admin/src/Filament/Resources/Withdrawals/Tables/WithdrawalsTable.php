<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\Withdrawals\Tables;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use He4rt\FakeBinance\Withdraw\Actions\CompleteWithdrawNow;
use He4rt\FakeBinance\Withdraw\Actions\EmitUnknownWithdrawStatus;
use He4rt\FakeBinance\Withdraw\Actions\ForceWithdrawStatus;
use He4rt\FakeBinance\Withdraw\Actions\SetWithdrawFrozen;
use He4rt\FakeBinance\Withdraw\Enums\WithdrawStatus;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;

class WithdrawalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('withdraw_order_id')
                    ->label(__('panel-admin::fake-binance.withdrawals.columns.withdraw_order_id'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('coin')
                    ->badge(),
                TextColumn::make('network'),
                TextColumn::make('amount')
                    ->numeric(decimalPlaces: 8)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('raw_status_override')
                    ->label(__('panel-admin::fake-binance.withdrawals.columns.unknown_code'))
                    ->placeholder('—')
                    ->color('danger'),
                TextColumn::make('info')
                    ->placeholder('—')
                    ->limit(30),
                TextColumn::make('tx_id')
                    ->label(__('panel-admin::fake-binance.withdrawals.columns.tx_id'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('frozen')
                    ->boolean()
                    ->label(__('panel-admin::fake-binance.withdrawals.columns.frozen')),
                TextColumn::make('applied_at')
                    ->dateTime(timezone: config('app.display_timezone'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(WithdrawStatus::class),
                TernaryFilter::make('frozen'),
            ])
            ->recordActions([
                self::completeNowAction(),
                self::forceStatusAction(),
                self::emitUnknownAction(),
                self::toggleFrozenAction(),
            ]);
    }

    /**
     * Os três status terminais de falha que a ticket documenta (1/3/5) — o
     * painel nunca oferece os demais, fora do escopo desta ação.
     *
     * @return array<int, string>
     */
    private static function forcibleStatuses(): array
    {
        return [
            WithdrawStatus::Cancelled->value => WithdrawStatus::Cancelled->getLabel(),
            WithdrawStatus::Rejected->value => WithdrawStatus::Rejected->getLabel(),
            WithdrawStatus::Failure->value => WithdrawStatus::Failure->getLabel(),
        ];
    }

    private static function completeNowAction(): Action
    {
        return Action::make('completeNow')
            ->label(__('panel-admin::fake-binance.withdrawals.actions.complete_now'))
            ->icon(Heroicon::OutlinedBolt)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('panel-admin::fake-binance.withdrawals.actions.complete_now_description'))
            ->action(function (Withdrawal $record): void {
                resolve(CompleteWithdrawNow::class)->handle($record);

                Notification::make()->title(__('panel-admin::fake-binance.withdrawals.actions.complete_now_notification'))->success()->send();
            });
    }

    private static function forceStatusAction(): Action
    {
        return Action::make('forceStatus')
            ->label(__('panel-admin::fake-binance.withdrawals.actions.force_status'))
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('danger')
            ->schema([
                Select::make('status')
                    ->label(__('panel-admin::fake-binance.withdrawals.actions.status_field'))
                    ->options(self::forcibleStatuses())
                    ->required(),
                TextInput::make('info')
                    ->label(__('panel-admin::fake-binance.withdrawals.actions.info_field'))
                    ->required(),
            ])
            ->action(function (array $data, Withdrawal $record): void {
                /** @var array{status: string, info: string} $data */
                resolve(ForceWithdrawStatus::class)->handle($record, WithdrawStatus::from((int) $data['status']), $data['info']);

                Notification::make()->title(__('panel-admin::fake-binance.withdrawals.actions.force_status_notification'))->success()->send();
            });
    }

    private static function emitUnknownAction(): Action
    {
        return Action::make('emitUnknown')
            ->label(__('panel-admin::fake-binance.withdrawals.actions.emit_unknown'))
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->color('warning')
            ->schema([
                TextInput::make('rawStatus')
                    ->label(__('panel-admin::fake-binance.withdrawals.actions.raw_status_field'))
                    ->numeric()
                    ->required(),
            ])
            ->action(function (array $data, Withdrawal $record): void {
                /** @var array{rawStatus: numeric-string} $data */
                resolve(EmitUnknownWithdrawStatus::class)->handle($record, (int) $data['rawStatus']);

                Notification::make()->title(__('panel-admin::fake-binance.withdrawals.actions.emit_unknown_notification'))->success()->send();
            });
    }

    private static function toggleFrozenAction(): Action
    {
        return Action::make('toggleFrozen')
            ->label(fn (Withdrawal $record): string => __('panel-admin::fake-binance.withdrawals.actions.'.($record->frozen ? 'unfreeze' : 'freeze')))
            ->icon(fn (Withdrawal $record): Heroicon => $record->frozen ? Heroicon::OutlinedPlay : Heroicon::OutlinedPause)
            ->color('gray')
            ->action(function (Withdrawal $record): void {
                resolve(SetWithdrawFrozen::class)->handle($record, !$record->frozen);

                Notification::make()->title(__('panel-admin::fake-binance.withdrawals.actions.frozen_notification'))->success()->send();
            });
    }
}
