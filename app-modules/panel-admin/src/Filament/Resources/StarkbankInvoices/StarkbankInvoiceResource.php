<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\StarkbankInvoices;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\PanelAdmin\Filament\Resources\StarkbankInvoices\Pages\ListStarkbankInvoices;
use He4rt\PanelAdmin\Filament\Resources\StarkbankInvoices\Tables\StarkbankInvoicesTable;
use UnitEnum;

/**
 * Observação e controle remoto da perna de cash-in. O happy path
 * (created → paid por idade, na leitura) atravessa sem nenhum clique — este
 * Resource existe para os desvios que o painel força sob comando.
 */
class StarkbankInvoiceResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = Invoice::class;

    protected static ?string $slug = 'starkbank-invoices';

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::FakeStarkbank;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Invoices';
    }

    public static function table(Table $table): Table
    {
        return StarkbankInvoicesTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListStarkbankInvoices::route('/'),
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
            'fake-starkbank.invoices',
        ];
    }
}
