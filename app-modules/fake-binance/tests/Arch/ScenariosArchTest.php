<?php

declare(strict_types=1);

use App\Models\BaseModel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/*
|--------------------------------------------------------------------------
| Scenarios sub-domain architecture rules
|--------------------------------------------------------------------------
|
| Scoped to `He4rt\FakeBinance\Scenarios\Models`/`Enums`, não ao namespace inteiro:
| um `toExtend`/`toImplement` module-wide falharia nas Actions/Middleware do
| mesmo sub-domínio.
|
*/

arch('scenarios models extend the shared BaseModel')
    ->expect('He4rt\FakeBinance\Scenarios\Models')
    ->toExtend(BaseModel::class);

arch('scenarios enums implement the Filament enum contracts')
    ->expect('He4rt\FakeBinance\Scenarios\Enums')
    ->toBeEnums()
    ->toImplement([HasColor::class, HasDescription::class, HasLabel::class]);
