<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\FiatOrders;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\PanelAdmin\Filament\Resources\FiatOrders\Pages\ListFiatOrders;
use He4rt\PanelAdmin\Filament\Resources\FiatOrders\Tables\FiatOrdersTable;
use UnitEnum;

/**
 * Controle remoto do cenário fiat: o happy path (crédito lazy) atravessa sem
 * nenhum clique — este Resource existe só para os desvios que o painel força
 * sob comando (ver `docs/admin/en/fake-binance/fiat-orders.md`).
 */
class FiatOrderResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = FiatOrder::class;

    protected static ?string $slug = 'fiat-orders';

    protected static ?string $recordTitleAttribute = 'order_no';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::FakeBinance;

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return FiatOrdersTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListFiatOrders::route('/'),
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
            'fake-binance.fiat-orders',
        ];
    }
}
