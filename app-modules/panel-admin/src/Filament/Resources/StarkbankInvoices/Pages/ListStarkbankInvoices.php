<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\StarkbankInvoices\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\PanelAdmin\Filament\Actions\ArmPixLegAction;
use He4rt\PanelAdmin\Filament\Resources\StarkbankInvoices\StarkbankInvoiceResource;

class ListStarkbankInvoices extends ListRecords
{
    protected static string $resource = StarkbankInvoiceResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ArmPixLegAction::make(PixLeg::StarkbankInvoice),
        ];
    }
}
