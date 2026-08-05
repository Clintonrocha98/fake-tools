<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\FiatOrders\Tables;

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
use He4rt\Venue\Fiat\Actions\CreditFiatOrderNow;
use He4rt\Venue\Fiat\Actions\DelayFiatBrcode;
use He4rt\Venue\Fiat\Actions\EmitUnknownFiatWireStatus;
use He4rt\Venue\Fiat\Actions\ForceFiatOrderStatus;
use He4rt\Venue\Fiat\Actions\SetFiatOrderFrozen;
use He4rt\Venue\Fiat\Enums\FiatOrderStatus;
use He4rt\Venue\Fiat\Models\FiatOrder;

class FiatOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_no')
                    ->label(__('panel-admin::venue.fiat_orders.columns.order_no'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('currency')
                    ->badge(),
                TextColumn::make('amount')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('panel-admin::venue.fiat_orders.columns.status_lazy'))
                    ->badge(),
                TextColumn::make('forced_status')
                    ->label(__('panel-admin::venue.fiat_orders.columns.override'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('forced_wire_status')
                    ->label(__('panel-admin::venue.fiat_orders.columns.unknown_wire'))
                    ->placeholder('—')
                    ->color('danger'),
                IconColumn::make('frozen')
                    ->boolean()
                    ->label(__('panel-admin::venue.fiat_orders.columns.frozen')),
                TextColumn::make('brcode_delay_reads')
                    ->label(__('panel-admin::venue.fiat_orders.columns.brcode_delay'))
                    ->placeholder('—')
                    ->description(fn (FiatOrder $record): string => __('panel-admin::venue.fiat_orders.columns.brcode_delay_description', ['count' => $record->brcode_reads_count])),
                TextColumn::make('credited_at')
                    ->dateTime(timezone: config('app.display_timezone'))
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime(timezone: config('app.display_timezone'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(FiatOrderStatus::class),
                TernaryFilter::make('frozen'),
            ])
            ->recordActions([
                self::creditNowAction(),
                self::forceStatusAction(),
                self::emitUnknownAction(),
                self::delayBrcodeAction(),
                self::toggleFrozenAction(),
            ]);
    }

    /**
     * As quatro falhas literais que a ticket documenta — o painel nunca
     * oferece as demais (Refunding/Refunded/…), fora do escopo desta ação.
     *
     * @return array<string, string>
     */
    private static function forcibleStatuses(): array
    {
        return [
            FiatOrderStatus::Failed->value => FiatOrderStatus::Failed->getLabel(),
            FiatOrderStatus::Expired->value => FiatOrderStatus::Expired->getLabel(),
            FiatOrderStatus::Cancelled->value => FiatOrderStatus::Cancelled->getLabel(),
            FiatOrderStatus::NeedAdditionalAction->value => FiatOrderStatus::NeedAdditionalAction->getLabel(),
        ];
    }

    private static function creditNowAction(): Action
    {
        return Action::make('creditNow')
            ->label(__('panel-admin::venue.fiat_orders.actions.credit_now'))
            ->icon(Heroicon::OutlinedBolt)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('panel-admin::venue.fiat_orders.actions.credit_now_description'))
            ->action(function (FiatOrder $record): void {
                resolve(CreditFiatOrderNow::class)->handle($record);

                Notification::make()->title(__('panel-admin::venue.fiat_orders.actions.credit_now_notification'))->success()->send();
            });
    }

    private static function forceStatusAction(): Action
    {
        return Action::make('forceStatus')
            ->label(__('panel-admin::venue.fiat_orders.actions.force_status'))
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('danger')
            ->schema([
                Select::make('status')
                    ->label(__('panel-admin::venue.fiat_orders.actions.status_field'))
                    ->options(self::forcibleStatuses())
                    ->required(),
            ])
            ->action(function (array $data, FiatOrder $record): void {
                /** @var array{status: string} $data */
                resolve(ForceFiatOrderStatus::class)->handle($record, FiatOrderStatus::from($data['status']));

                Notification::make()->title(__('panel-admin::venue.fiat_orders.actions.force_status_notification'))->success()->send();
            });
    }

    private static function emitUnknownAction(): Action
    {
        return Action::make('emitUnknown')
            ->label(__('panel-admin::venue.fiat_orders.actions.emit_unknown'))
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->color('warning')
            ->schema([
                TextInput::make('wireStatus')
                    ->label(__('panel-admin::venue.fiat_orders.actions.wire_status_field'))
                    ->required(),
            ])
            ->action(function (array $data, FiatOrder $record): void {
                /** @var array{wireStatus: string} $data */
                resolve(EmitUnknownFiatWireStatus::class)->handle($record, $data['wireStatus']);

                Notification::make()->title(__('panel-admin::venue.fiat_orders.actions.emit_unknown_notification'))->success()->send();
            });
    }

    private static function delayBrcodeAction(): Action
    {
        return Action::make('delayBrcode')
            ->label(__('panel-admin::venue.fiat_orders.actions.delay_brcode'))
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->fillForm(fn (FiatOrder $record): array => ['reads' => $record->brcode_delay_reads])
            ->schema([
                TextInput::make('reads')
                    ->label(__('panel-admin::venue.fiat_orders.actions.reads_field'))
                    ->numeric()
                    ->minValue(0)
                    ->helperText(__('panel-admin::venue.fiat_orders.actions.reads_helper')),
            ])
            ->action(function (array $data, FiatOrder $record): void {
                /** @var array{reads: string|int|null} $data */
                $reads = $data['reads'];
                resolve(DelayFiatBrcode::class)->handle($record, $reads === null || $reads === '' ? null : (int) $reads);

                Notification::make()->title(__('panel-admin::venue.fiat_orders.actions.delay_brcode_notification'))->success()->send();
            });
    }

    private static function toggleFrozenAction(): Action
    {
        return Action::make('toggleFrozen')
            ->label(fn (FiatOrder $record): string => __('panel-admin::venue.fiat_orders.actions.'.($record->frozen ? 'unfreeze' : 'freeze')))
            ->icon(fn (FiatOrder $record): Heroicon => $record->frozen ? Heroicon::OutlinedPlay : Heroicon::OutlinedPause)
            ->color('gray')
            ->action(function (FiatOrder $record): void {
                resolve(SetFiatOrderFrozen::class)->handle($record, !$record->frozen);

                Notification::make()->title(__('panel-admin::venue.fiat_orders.actions.frozen_notification'))->success()->send();
            });
    }
}
