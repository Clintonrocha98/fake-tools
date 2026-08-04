<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\LedgerAccounts\Tables;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use He4rt\Venue\Ledger\Actions\SetLedgerBalance;
use He4rt\Venue\Ledger\Models\LedgerAccount;

class LedgerAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('asset')
                    ->badge()
                    ->searchable(),
                TextColumn::make('free')
                    ->numeric(decimalPlaces: 8)
                    ->sortable(),
                TextColumn::make('locked')
                    ->numeric(decimalPlaces: 8)
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime(timezone: config('app.display_timezone'))
                    ->sortable(),
            ])
            ->headerActions([
                self::setNewAssetAction(),
            ])
            ->recordActions([
                self::editBalanceAction(),
            ]);
    }

    private static function setNewAssetAction(): Action
    {
        return Action::make('setNewAsset')
            ->label(__('panel-admin::venue.ledger_accounts.actions.new_balance'))
            ->icon(Heroicon::OutlinedPlus)
            ->schema([
                TextInput::make('asset')
                    ->label(__('panel-admin::venue.ledger_accounts.actions.asset_field'))
                    ->required(),
                TextInput::make('free')
                    ->numeric()
                    ->required()
                    ->default('0'),
                TextInput::make('locked')
                    ->numeric()
                    ->required()
                    ->default('0'),
            ])
            ->action(function (array $data): void {
                /** @var array{asset: string, free: float, locked: float} $data */
                resolve(SetLedgerBalance::class)($data['asset'], (string) $data['free'], (string) $data['locked']);

                Notification::make()->title(__('panel-admin::venue.ledger_accounts.actions.new_balance_notification'))->success()->send();
            });
    }

    private static function editBalanceAction(): Action
    {
        return Action::make('editBalance')
            ->label(__('panel-admin::venue.ledger_accounts.actions.edit_balance'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->fillForm(fn (LedgerAccount $record): array => [
                'free' => (string) $record->free,
                'locked' => (string) $record->locked,
            ])
            ->schema([
                TextInput::make('free')
                    ->numeric()
                    ->required(),
                TextInput::make('locked')
                    ->numeric()
                    ->required(),
            ])
            ->action(function (array $data, LedgerAccount $record): void {
                /** @var array{free: float, locked: float} $data */
                resolve(SetLedgerBalance::class)($record->asset, (string) $data['free'], (string) $data['locked']);

                Notification::make()->title(__('panel-admin::venue.ledger_accounts.actions.edit_balance_notification'))->success()->send();
            });
    }
}
