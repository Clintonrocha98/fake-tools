<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\StarkbankBrcodePayments\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\PanelAdmin\Filament\Actions\ArmPixLegAction;
use He4rt\PanelAdmin\Filament\Resources\StarkbankBrcodePayments\StarkbankBrcodePaymentResource;

class ListStarkbankBrcodePayments extends ListRecords
{
    protected static string $resource = StarkbankBrcodePaymentResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ArmPixLegAction::make(PixLeg::StarkbankBrcodePayment),
        ];
    }
}
