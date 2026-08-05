<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\SpotOrders\Tables;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use He4rt\FakeBinance\Spot\Actions\EmitUnknownSpotOrderStatus;
use He4rt\FakeBinance\Spot\Actions\ExpireSpotOrderPartially;
use He4rt\FakeBinance\Spot\Actions\RejectSpotOrder;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;
use He4rt\FakeBinance\Spot\Models\SpotOrder;

class SpotOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('symbol')
                    ->searchable(),
                TextColumn::make('client_order_id')
                    ->label(__('panel-admin::fake-binance.spot_orders.columns.client_order_id'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('side')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('raw_status_override')
                    ->label(__('panel-admin::fake-binance.spot_orders.columns.unknown_wire'))
                    ->placeholder('—')
                    ->color('danger'),
                TextColumn::make('executed_qty')
                    ->label(__('panel-admin::fake-binance.spot_orders.columns.executed_qty'))
                    ->numeric(decimalPlaces: 8),
                TextColumn::make('cummulative_quote_qty')
                    ->label(__('panel-admin::fake-binance.spot_orders.columns.quote_qty'))
                    ->numeric(decimalPlaces: 8),
                TextColumn::make('fill_price')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime(timezone: config('app.display_timezone'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderStatus::class),
            ])
            ->recordActions([
                self::rejectAction(),
                self::expirePartiallyAction(),
                self::emitUnknownAction(),
            ]);
    }

    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('panel-admin::fake-binance.spot_orders.actions.reject'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('panel-admin::fake-binance.spot_orders.actions.reject_description'))
            ->action(function (SpotOrder $record): void {
                resolve(RejectSpotOrder::class)->handle($record);

                Notification::make()->title(__('panel-admin::fake-binance.spot_orders.actions.reject_notification'))->success()->send();
            });
    }

    private static function expirePartiallyAction(): Action
    {
        return Action::make('expirePartially')
            ->label(__('panel-admin::fake-binance.spot_orders.actions.expire_partially'))
            ->icon(Heroicon::OutlinedClock)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(__('panel-admin::fake-binance.spot_orders.actions.expire_partially_description'))
            ->action(function (SpotOrder $record): void {
                resolve(ExpireSpotOrderPartially::class)->handle($record);

                Notification::make()->title(__('panel-admin::fake-binance.spot_orders.actions.expire_partially_notification'))->success()->send();
            });
    }

    private static function emitUnknownAction(): Action
    {
        return Action::make('emitUnknown')
            ->label(__('panel-admin::fake-binance.spot_orders.actions.emit_unknown'))
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->color('gray')
            ->schema([
                TextInput::make('rawStatus')
                    ->label(__('panel-admin::fake-binance.spot_orders.actions.raw_status_field'))
                    ->required(),
            ])
            ->action(function (array $data, SpotOrder $record): void {
                /** @var array{rawStatus: string} $data */
                resolve(EmitUnknownSpotOrderStatus::class)->handle($record, $data['rawStatus']);

                Notification::make()->title(__('panel-admin::fake-binance.spot_orders.actions.emit_unknown_notification'))->success()->send();
            });
    }
}
