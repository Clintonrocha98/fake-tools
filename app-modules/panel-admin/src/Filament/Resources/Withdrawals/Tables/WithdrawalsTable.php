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
use He4rt\Venue\Withdraw\Actions\CompleteWithdrawNow;
use He4rt\Venue\Withdraw\Actions\EmitUnknownWithdrawStatus;
use He4rt\Venue\Withdraw\Actions\ForceWithdrawStatus;
use He4rt\Venue\Withdraw\Actions\SetWithdrawFrozen;
use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Models\Withdrawal;

class WithdrawalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('withdraw_order_id')
                    ->label('Withdraw order id')
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
                    ->label('Código desconhecido')
                    ->placeholder('—')
                    ->color('danger'),
                TextColumn::make('info')
                    ->placeholder('—')
                    ->limit(30),
                TextColumn::make('tx_id')
                    ->label('Tx id')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('frozen')
                    ->boolean()
                    ->label('Congelado'),
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
            ->label('Completar agora')
            ->icon(Heroicon::OutlinedBolt)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Pula o relógio do avanço lazy e conclui o withdraw imediatamente.')
            ->action(function (Withdrawal $record): void {
                resolve(CompleteWithdrawNow::class)($record);

                Notification::make()->title('Withdraw concluído')->success()->send();
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
                TextInput::make('info')
                    ->label('Motivo (info)')
                    ->required(),
            ])
            ->action(function (array $data, Withdrawal $record): void {
                /** @var array{status: string, info: string} $data */
                resolve(ForceWithdrawStatus::class)($record, WithdrawStatus::from((int) $data['status']), $data['info']);

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
                TextInput::make('rawStatus')
                    ->label('Código de status arbitrário')
                    ->numeric()
                    ->required(),
            ])
            ->action(function (array $data, Withdrawal $record): void {
                /** @var array{rawStatus: numeric-string} $data */
                resolve(EmitUnknownWithdrawStatus::class)($record, (int) $data['rawStatus']);

                Notification::make()->title('Vocabulário desconhecido emitido')->success()->send();
            });
    }

    private static function toggleFrozenAction(): Action
    {
        return Action::make('toggleFrozen')
            ->label(fn (Withdrawal $record): string => $record->frozen ? 'Descongelar' : 'Congelar')
            ->icon(fn (Withdrawal $record): Heroicon => $record->frozen ? Heroicon::OutlinedPlay : Heroicon::OutlinedPause)
            ->color('gray')
            ->action(function (Withdrawal $record): void {
                resolve(SetWithdrawFrozen::class)($record, !$record->frozen);

                Notification::make()->title('Congelamento atualizado')->success()->send();
            });
    }
}
