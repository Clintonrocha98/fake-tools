<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\StarkbankDictEntries\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use He4rt\FakeStarkbank\Dict\Actions\RegisterDictKey;
use He4rt\FakeStarkbank\Dict\DTOs\RegisterDictKeyData;
use He4rt\FakeStarkbank\Dict\Enums\DictKeyType;
use He4rt\FakeStarkbank\Dict\Enums\DictOwnerType;
use He4rt\PanelAdmin\Filament\Resources\StarkbankDictEntries\StarkbankDictEntryResource;

class ListStarkbankDictEntries extends ListRecords
{
    protected static string $resource = StarkbankDictEntryResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->registerAction(),
        ];
    }

    private function registerAction(): Action
    {
        return Action::make('registerDictKey')
            ->label(__('panel-admin::fake-starkbank.dict_entries.actions.register'))
            ->icon(Heroicon::OutlinedKey)
            ->color('primary')
            ->schema([
                TextInput::make('pix_key')
                    ->label(__('panel-admin::fake-starkbank.dict_entries.columns.pix_key'))
                    ->required(),
                Select::make('type')
                    ->options(DictKeyType::class)
                    ->default(DictKeyType::Email->value)
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('tax_id')
                    ->label(__('panel-admin::fake-starkbank.dict_entries.columns.tax_id'))
                    ->required(),
                Select::make('owner_type')
                    ->options(DictOwnerType::class)
                    ->default(DictOwnerType::NaturalPerson->value)
                    ->required(),
                TextInput::make('bank_name')
                    ->label(__('panel-admin::fake-starkbank.dict_entries.columns.bank_name'))
                    ->default(config('fake-starkbank-dict.bank.name')),
                TextInput::make('ispb')
                    ->label(__('panel-admin::fake-starkbank.dict_entries.columns.ispb'))
                    ->default(config('fake-starkbank-dict.bank.ispb')),
                TextInput::make('account_type')
                    ->label(__('panel-admin::fake-starkbank.dict_entries.columns.account_type'))
                    ->default(config('fake-starkbank-dict.bank.account_type')),
            ])
            ->action(function (array $data): void {
                /** @var array<string, mixed> $data */
                resolve(RegisterDictKey::class)->handle(RegisterDictKeyData::fromForm($data));

                Notification::make()
                    ->title(__('panel-admin::fake-starkbank.dict_entries.actions.register_notification'))
                    ->success()
                    ->send();
            });
    }
}
