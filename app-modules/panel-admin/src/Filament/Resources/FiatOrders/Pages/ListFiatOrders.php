<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\FiatOrders\Pages;

use Filament\Resources\Pages\ListRecords;
use He4rt\PanelAdmin\Filament\Resources\FiatOrders\FiatOrderResource;

class ListFiatOrders extends ListRecords
{
    protected static string $resource = FiatOrderResource::class;
}
