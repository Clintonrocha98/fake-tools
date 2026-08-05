<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\SpotOrders;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use He4rt\PanelAdmin\Filament\Resources\SpotOrders\Pages\ListSpotOrders;
use He4rt\PanelAdmin\Filament\Resources\SpotOrders\Tables\SpotOrdersTable;
use UnitEnum;

/**
 * Controle remoto do cenário spot: o happy path (MARKET sempre FILLED)
 * atravessa sem nenhum clique — este Resource existe só para os desvios que
 * o painel força sob comando (ver `docs/admin/en/fake-binance/spot-orders.md`).
 */
class SpotOrderResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = SpotOrder::class;

    protected static ?string $slug = 'spot-orders';

    protected static ?string $recordTitleAttribute = 'client_order_id';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::FakeBinance;

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return SpotOrdersTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListSpotOrders::route('/'),
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
            'fake-binance.spot-orders',
        ];
    }
}
