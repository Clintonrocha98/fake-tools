<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\StarkbankTransfers;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\PanelAdmin\Filament\Resources\StarkbankTransfers\Pages\ListStarkbankTransfers;
use He4rt\PanelAdmin\Filament\Resources\StarkbankTransfers\Tables\StarkbankTransfersTable;
use UnitEnum;

/**
 * Observação e controle remoto da perna de cash-out. O happy path
 * (created → processing → success por idade, na leitura) atravessa sem nenhum
 * clique — este Resource existe para os desvios que o painel força sob comando.
 */
class StarkbankTransferResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Transfer::class;

    protected static ?string $slug = 'starkbank-transfers';

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpOnSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::FakeStarkbank;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Transfers';
    }

    public static function table(Table $table): Table
    {
        return StarkbankTransfersTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListStarkbankTransfers::route('/'),
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
            'fake-starkbank.transfers',
        ];
    }
}
