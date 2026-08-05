<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\LedgerAccounts;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\PanelAdmin\Filament\Resources\LedgerAccounts\Pages\ListLedgerAccounts;
use He4rt\PanelAdmin\Filament\Resources\LedgerAccounts\Tables\LedgerAccountsTable;
use UnitEnum;

/**
 * Saldos do ledger, editáveis sob comando — sempre via
 * {@see \He4rt\FakeBinance\Ledger\Actions\SetLedgerBalance}, nunca um form Eloquent
 * padrão, para que a sobrescrita (em vez do delta relativo de
 * Credit/DebitLedgerAccount) fique explícita em um único lugar.
 */
class LedgerAccountResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = LedgerAccount::class;

    protected static ?string $slug = 'ledger-accounts';

    protected static ?string $recordTitleAttribute = 'asset';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::FakeBinance;

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return LedgerAccountsTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListLedgerAccounts::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * @return string[]
     */
    public static function getDocumentation(): array
    {
        return [
            'fake-binance.ledger-accounts',
        ];
    }
}
