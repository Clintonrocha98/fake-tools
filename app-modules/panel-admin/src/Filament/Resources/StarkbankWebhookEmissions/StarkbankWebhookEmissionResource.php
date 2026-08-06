<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\StarkbankWebhookEmissions;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use He4rt\PanelAdmin\Filament\Resources\StarkbankWebhookEmissions\Pages\ListStarkbankWebhookEmissions;
use He4rt\PanelAdmin\Filament\Resources\StarkbankWebhookEmissions\Tables\StarkbankWebhookEmissionsTable;
use UnitEnum;

/**
 * A fila de emissões de webhook: o que o fake montou, assinou e tentou
 * entregar. Observação em primeiro lugar — é aqui que se vê um 401 do
 * consumidor sem abrir log —, e as três ações de cenário que o ticket de
 * webhook expõe (reenviar, corromper, liberar represada).
 */
class StarkbankWebhookEmissionResource extends Resource implements HasKnowledgeBase
{
    protected static ?string $model = WebhookEmission::class;

    protected static ?string $slug = 'starkbank-webhook-emissions';

    protected static ?string $recordTitleAttribute = 'event_id';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::FakeStarkbank;

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return 'Webhooks';
    }

    public static function table(Table $table): Table
    {
        return StarkbankWebhookEmissionsTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListStarkbankWebhookEmissions::route('/'),
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
            'fake-starkbank.webhook-emissions',
        ];
    }
}
