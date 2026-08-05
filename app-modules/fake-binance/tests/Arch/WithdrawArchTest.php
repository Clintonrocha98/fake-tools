<?php

declare(strict_types=1);

use App\Models\BaseModel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/*
|--------------------------------------------------------------------------
| Withdraw sub-domain architecture rules
|--------------------------------------------------------------------------
|
| Scoped a `He4rt\FakeBinance\Withdraw\Models` e `He4rt\FakeBinance\Withdraw\Enums`, não ao
| namespace `He4rt\FakeBinance\Withdraw` inteiro: um `toExtend`/`toImplement` module-wide
| falharia nas Actions/DTOs/Controllers do mesmo sub-domínio.
|
*/

arch('withdraw models extend the shared BaseModel')
    ->expect('He4rt\FakeBinance\Withdraw\Models')
    ->toExtend(BaseModel::class);

arch('withdraw enums implement the Filament enum contracts')
    ->expect('He4rt\FakeBinance\Withdraw\Enums')
    ->toImplement([HasColor::class, HasDescription::class, HasLabel::class]);
