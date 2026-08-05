<?php

declare(strict_types=1);

use App\Models\BaseModel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/*
|--------------------------------------------------------------------------
| Fiat sub-domain architecture rules
|--------------------------------------------------------------------------
|
| Escopado a `He4rt\FakeBinance\Fiat`, no mesmo espírito de LedgerArchTest: um
| `toExtend(BaseModel::class)` module-wide quebraria em classes não-Eloquent
| de outras pernas.
|
*/

arch('fiat models extend the shared BaseModel')
    ->expect('He4rt\FakeBinance\Fiat\Models')
    ->toExtend(BaseModel::class);

arch('fiat enums implement the Filament enum contracts')
    ->expect('He4rt\FakeBinance\Fiat\Enums')
    ->toBeEnums()
    ->toImplement(HasLabel::class)
    ->toImplement(HasColor::class)
    ->toImplement(HasDescription::class);
