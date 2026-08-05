<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\SpotOrders\Pages;

use Filament\Resources\Pages\ListRecords;
use He4rt\PanelAdmin\Filament\Resources\SpotOrders\SpotOrderResource;

class ListSpotOrders extends ListRecords
{
    protected static string $resource = SpotOrderResource::class;
}
