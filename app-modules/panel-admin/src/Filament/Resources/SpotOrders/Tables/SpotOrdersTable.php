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
use He4rt\Venue\Spot\Actions\EmitUnknownSpotOrderStatus;
use He4rt\Venue\Spot\Actions\ExpireSpotOrderPartially;
use He4rt\Venue\Spot\Actions\RejectSpotOrder;
use He4rt\Venue\Spot\Enums\OrderStatus;
use He4rt\Venue\Spot\Models\SpotOrder;

class SpotOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('symbol')
                    ->searchable(),
                TextColumn::make('client_order_id')
                    ->label('Client order id')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('side')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('raw_status_override')
                    ->label('Wire desconhecido')
                    ->placeholder('—')
                    ->color('danger'),
                TextColumn::make('executed_qty')
                    ->label('Executed qty')
                    ->numeric(decimalPlaces: 8),
                TextColumn::make('cummulative_quote_qty')
                    ->label('Quote qty')
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
            ->label('Recusar (REJECTED)')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Zera o fill e move a ordem para REJECTED — sem execução alguma.')
            ->action(function (SpotOrder $record): void {
                resolve(RejectSpotOrder::class)($record);

                Notification::make()->title('Ordem recusada')->success()->send();
            });
    }

    private static function expirePartiallyAction(): Action
    {
        return Action::make('expirePartially')
            ->label('Preencher parcial + EXPIRED')
            ->icon(Heroicon::OutlinedClock)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('Reduz o fill à metade do executado e move a ordem para EXPIRED.')
            ->action(function (SpotOrder $record): void {
                resolve(ExpireSpotOrderPartially::class)($record);

                Notification::make()->title('Ordem preenchida parcialmente e expirada')->success()->send();
            });
    }

    private static function emitUnknownAction(): Action
    {
        return Action::make('emitUnknown')
            ->label('Emitir vocabulário desconhecido')
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->color('gray')
            ->schema([
                TextInput::make('rawStatus')
                    ->label('Status arbitrário')
                    ->required(),
            ])
            ->action(function (array $data, SpotOrder $record): void {
                /** @var array{rawStatus: string} $data */
                resolve(EmitUnknownSpotOrderStatus::class)($record, $data['rawStatus']);

                Notification::make()->title('Vocabulário desconhecido emitido')->success()->send();
            });
    }
}
