<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\StarkbankDictEntries\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use He4rt\FakeStarkbank\Dict\Enums\DictKeyType;
use He4rt\FakeStarkbank\Dict\Enums\DictOwnerType;

class StarkbankDictEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pix_key')
                    ->label(__('panel-admin::fake-starkbank.dict_entries.columns.pix_key'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('tax_id')
                    ->label(__('panel-admin::fake-starkbank.dict_entries.columns.tax_id'))
                    ->searchable(),
                TextColumn::make('owner_type')
                    ->badge(),
                TextColumn::make('bank_name')
                    ->label(__('panel-admin::fake-starkbank.dict_entries.columns.bank_name'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ispb')
                    ->label(__('panel-admin::fake-starkbank.dict_entries.columns.ispb'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('account_type')
                    ->label(__('panel-admin::fake-starkbank.dict_entries.columns.account_type'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(DictKeyType::class),
                SelectFilter::make('owner_type')
                    ->options(DictOwnerType::class),
            ]);
    }
}
