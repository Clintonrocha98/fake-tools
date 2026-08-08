<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\StarkbankWebhookEmissions\Tables;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use He4rt\FakeStarkbank\Webhook\Actions\EmitCorrupted;
use He4rt\FakeStarkbank\Webhook\Actions\ReleaseEmissionHold;
use He4rt\FakeStarkbank\Webhook\Actions\ReplayEmission;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

class StarkbankWebhookEmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_id')
                    ->label(__('panel-admin::fake-starkbank.webhook_emissions.columns.event_id'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('subscription')
                    ->badge(),
                TextColumn::make('event_type')
                    ->badge(),
                TextColumn::make('entity_id')
                    ->label(__('panel-admin::fake-starkbank.webhook_emissions.columns.entity_id'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('response_code')
                    ->label(__('panel-admin::fake-starkbank.webhook_emissions.columns.response_code'))
                    ->placeholder('—'),
                IconColumn::make('held_at')
                    ->label(__('panel-admin::fake-starkbank.webhook_emissions.columns.held'))
                    ->boolean()
                    ->state(fn (WebhookEmission $record): bool => $record->isHeld()),
                TextColumn::make('sent_at')
                    ->label(__('panel-admin::fake-starkbank.webhook_emissions.columns.sent_at'))
                    ->dateTime(timezone: config('app.display_timezone'))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('failed_reason')
                    ->label(__('panel-admin::fake-starkbank.webhook_emissions.columns.failed_reason'))
                    ->placeholder('—')
                    ->limit(30)
                    ->color('danger'),
                TextColumn::make('created_at')
                    ->dateTime(timezone: config('app.display_timezone'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('subscription')
                    ->options(StarkbankSubscription::class),
                SelectFilter::make('event_type')
                    ->options(StarkbankEventType::class),
                TernaryFilter::make('held_at')
                    ->label(__('panel-admin::fake-starkbank.webhook_emissions.columns.held'))
                    ->nullable(),
            ])
            ->recordActions([
                self::releaseHoldAction(),
                self::replayAction(),
                self::emitCorruptedAction(),
            ]);
    }

    private static function releaseHoldAction(): Action
    {
        return Action::make('releaseHold')
            ->label(__('panel-admin::fake-starkbank.webhook_emissions.actions.release_hold'))
            ->icon(Heroicon::OutlinedPlay)
            ->color('success')
            ->visible(fn (WebhookEmission $record): bool => $record->isHeld())
            ->requiresConfirmation()
            ->modalDescription(__('panel-admin::fake-starkbank.webhook_emissions.actions.release_hold_description'))
            ->action(function (WebhookEmission $record): void {
                resolve(ReleaseEmissionHold::class)($record);

                Notification::make()->title(__('panel-admin::fake-starkbank.webhook_emissions.actions.release_hold_notification'))->success()->send();
            });
    }

    private static function replayAction(): Action
    {
        return Action::make('replay')
            ->label(__('panel-admin::fake-starkbank.webhook_emissions.actions.replay'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('panel-admin::fake-starkbank.webhook_emissions.actions.replay_description'))
            ->action(function (WebhookEmission $record): void {
                resolve(ReplayEmission::class)($record);

                Notification::make()->title(__('panel-admin::fake-starkbank.webhook_emissions.actions.replay_notification'))->success()->send();
            });
    }

    private static function emitCorruptedAction(): Action
    {
        return Action::make('emitCorrupted')
            ->label(__('panel-admin::fake-starkbank.webhook_emissions.actions.emit_corrupted'))
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('panel-admin::fake-starkbank.webhook_emissions.actions.emit_corrupted_description'))
            ->action(function (WebhookEmission $record): void {
                resolve(EmitCorrupted::class)($record->subscription, $record->event_type, $record->entity());

                Notification::make()->title(__('panel-admin::fake-starkbank.webhook_emissions.actions.emit_corrupted_notification'))->success()->send();
            });
    }
}
