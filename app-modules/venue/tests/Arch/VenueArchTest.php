<?php

declare(strict_types=1);

use App\Models\BaseModel;

/*
|--------------------------------------------------------------------------
| Venue module architecture rules
|--------------------------------------------------------------------------
|
| Module-scoped conventions: the ledger is a domain concern (never depends on the
| presentation layer) and its Eloquent models extend the shared BaseModel so they
| inherit UUIDs, activity logging and factory wiring.
|
*/

arch('the venue domain module declares strict types')
    ->expect('He4rt\Venue')
    ->toUseStrictTypes();

arch('domain code never produces a stdClass')
    ->expect('He4rt\Venue')
    ->not->toUse('stdClass');

arch('domain modules do not depend on the presentation layer')
    ->expect('He4rt\Venue')
    ->not->toUse('He4rt\PanelAdmin');

arch('ledger models extend the shared BaseModel')
    ->expect('He4rt\Venue\Ledger\Models')
    ->toExtend(BaseModel::class);
