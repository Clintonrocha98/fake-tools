<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\StarkbankDictEntries;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use He4rt\PanelAdmin\Filament\Resources\StarkbankDictEntries\Pages\ListStarkbankDictEntries;
use He4rt\PanelAdmin\Filament\Resources\StarkbankDictEntries\Tables\StarkbankDictEntriesTable;
use UnitEnum;

/**
 * O registro DICT deste fake: as chaves PIX que um cash-out consegue resolver.
 * O seeder já semeia as duas do fluxo padrão — aqui o operador registra uma
 * chave de dev nova sem mexer em config.
 *
 * `canCreate()` é falso de propósito: a criação passa pela ação de cabeçalho,
 * que embrulha a Action de domínio em vez de gravar o model direto — os blobs
 * opacos de agência e conta são derivados da chave, nunca digitados.
 */
class StarkbankDictEntryResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = DictEntry::class;

    protected static ?string $slug = 'starkbank-dict-entries';

    protected static ?string $recordTitleAttribute = 'pix_key';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::FakeStarkbank;

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return 'DICT';
    }

    public static function table(Table $table): Table
    {
        return StarkbankDictEntriesTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListStarkbankDictEntries::route('/'),
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
            'fake-starkbank.dict-entries',
        ];
    }
}
