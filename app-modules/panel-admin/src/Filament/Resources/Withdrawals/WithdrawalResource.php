<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\Withdrawals;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use He4rt\PanelAdmin\Filament\Resources\Withdrawals\Pages\ListWithdrawals;
use He4rt\PanelAdmin\Filament\Resources\Withdrawals\Tables\WithdrawalsTable;
use He4rt\Venue\Withdraw\Models\Withdrawal;
use UnitEnum;

/**
 * Controle remoto do cenário de withdraw: o happy path (2 → 4 → 6 lazy)
 * atravessa sem nenhum clique — este Resource existe só para os desvios que
 * o painel força sob comando (ver `docs/admin/en/venue/withdrawals.md`).
 */
class WithdrawalResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Withdrawal::class;

    protected static ?string $slug = 'withdrawals';

    protected static ?string $recordTitleAttribute = 'withdraw_order_id';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpOnSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Venue;

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return WithdrawalsTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListWithdrawals::route('/'),
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
            'venue.withdrawals',
        ];
    }
}
