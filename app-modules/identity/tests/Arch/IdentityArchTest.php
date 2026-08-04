<?php

declare(strict_types=1);

use App\Models\BaseModel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/*
|--------------------------------------------------------------------------
| Identity module architecture rules
|--------------------------------------------------------------------------
|
| Module-scoped conventions: external-identity enums implement the Filament
| HasLabel contract (guideline 07), and its Eloquent models extend the shared
| BaseModel so they inherit UUIDs, activity logging and factory wiring.
|
*/

arch('the identity domain module declares strict types')
    ->expect('He4rt\Identity')
    ->toUseStrictTypes();

arch('domain code never produces a stdClass')
    ->expect('He4rt\Identity')
    ->not->toUse('stdClass');

arch('domain modules do not depend on the presentation layer')
    ->expect('He4rt\Identity')
    ->not->toUse('He4rt\PanelAdmin');

arch('external identity enums are backed enums implementing HasLabel')
    ->expect('He4rt\Identity\ExternalIdentity\Enums')
    ->toBeEnums()
    ->toImplement([HasLabel::class, HasDescription::class, HasColor::class]);

arch('external identity models extend the shared BaseModel')
    ->expect('He4rt\Identity\ExternalIdentity\Models')
    ->toExtend(BaseModel::class);
