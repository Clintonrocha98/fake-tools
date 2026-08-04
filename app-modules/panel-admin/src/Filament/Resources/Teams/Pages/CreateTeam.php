<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\Teams\Pages;

use Filament\Resources\Pages\CreateRecord;
use He4rt\PanelAdmin\Filament\Resources\Teams\TeamResource;

class CreateTeam extends CreateRecord
{
    protected static string $resource = TeamResource::class;
}
