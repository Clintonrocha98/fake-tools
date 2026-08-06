<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\StarkbankBrcodePayments;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\PanelAdmin\Filament\Resources\StarkbankBrcodePayments\Pages\ListStarkbankBrcodePayments;
use He4rt\PanelAdmin\Filament\Resources\StarkbankBrcodePayments\Tables\StarkbankBrcodePaymentsTable;
use UnitEnum;

/**
 * Observação e controle remoto da perna de funding da venue. O happy path
 * (created → processing → success por idade, na leitura) atravessa sem nenhum
 * clique — este Resource existe para os desvios que o painel força sob comando.
 */
class StarkbankBrcodePaymentResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = BrcodePayment::class;

    protected static ?string $slug = 'starkbank-brcode-payments';

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::FakeStarkbank;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return 'BR Code payments';
    }

    public static function table(Table $table): Table
    {
        return StarkbankBrcodePaymentsTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListStarkbankBrcodePayments::route('/'),
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
            'fake-starkbank.brcode-payments',
        ];
    }
}
