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
                    ->label('Order no')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('currency')
                    ->badge(),
                TextColumn::make('amount')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status (lazy)')
                    ->badge(),
                TextColumn::make('forced_status')
                    ->label('Override')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('forced_wire_status')
                    ->label('Wire desconhecido')
                    ->placeholder('—')
                    ->color('danger'),
                IconColumn::make('frozen')
                    ->boolean()
                    ->label('Congelada'),
                TextColumn::make('brcode_delay_reads')
                    ->label('Atraso brcode')
                    ->placeholder('—')
                    ->description(fn (FiatOrder $record): string => sprintf('lidas: %d', $record->brcode_reads_count)),
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
            ->label('Creditar agora')
            ->icon(Heroicon::OutlinedBolt)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Pula o relógio do avanço lazy e credita o ledger imediatamente.')
            ->action(function (FiatOrder $record): void {
                resolve(CreditFiatOrderNow::class)($record);

                Notification::make()->title('Ordem creditada')->success()->send();
            });
    }

    private static function forceStatusAction(): Action
    {
        return Action::make('forceStatus')
            ->label('Falhar com status')
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('danger')
            ->schema([
                Select::make('status')
                    ->label('Status')
                    ->options(self::forcibleStatuses())
                    ->required(),
            ])
            ->action(function (array $data, FiatOrder $record): void {
                /** @var array{status: string} $data */
                resolve(ForceFiatOrderStatus::class)($record, FiatOrderStatus::from($data['status']));

                Notification::make()->title('Status forçado')->success()->send();
            });
    }

    private static function emitUnknownAction(): Action
    {
        return Action::make('emitUnknown')
            ->label('Emitir vocabulário desconhecido')
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->color('warning')
            ->schema([
                TextInput::make('wireStatus')
                    ->label('Status de wire arbitrário')
                    ->required(),
            ])
            ->action(function (array $data, FiatOrder $record): void {
                /** @var array{wireStatus: string} $data */
                resolve(EmitUnknownFiatWireStatus::class)($record, $data['wireStatus']);

                Notification::make()->title('Vocabulário desconhecido emitido')->success()->send();
            });
    }

    private static function delayBrcodeAction(): Action
    {
        return Action::make('delayBrcode')
            ->label('Atrasar brcode')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->fillForm(fn (FiatOrder $record): array => ['reads' => $record->brcode_delay_reads])
            ->schema([
                TextInput::make('reads')
                    ->label('Leituras sem brcode')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Vazio remove o atraso — brcode volta a aparecer imediatamente.'),
            ])
            ->action(function (array $data, FiatOrder $record): void {
                /** @var array{reads: string|int|null} $data */
                $reads = $data['reads'];
                resolve(DelayFiatBrcode::class)($record, $reads === null || $reads === '' ? null : (int) $reads);

                Notification::make()->title('Atraso de brcode atualizado')->success()->send();
            });
    }

    private static function toggleFrozenAction(): Action
    {
        return Action::make('toggleFrozen')
            ->label(fn (FiatOrder $record): string => $record->frozen ? 'Descongelar' : 'Congelar')
            ->icon(fn (FiatOrder $record): Heroicon => $record->frozen ? Heroicon::OutlinedPlay : Heroicon::OutlinedPause)
            ->color('gray')
            ->action(function (FiatOrder $record): void {
                resolve(SetFiatOrderFrozen::class)($record, !$record->frozen);

                Notification::make()->title('Congelamento atualizado')->success()->send();
            });
    }
}
