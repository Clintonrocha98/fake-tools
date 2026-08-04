<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\LedgerAccounts\Pages;

use Filament\Resources\Pages\ListRecords;
use He4rt\PanelAdmin\Filament\Resources\LedgerAccounts\LedgerAccountResource;

class ListLedgerAccounts extends ListRecords
{
    protected static string $resource = LedgerAccountResource::class;
}
