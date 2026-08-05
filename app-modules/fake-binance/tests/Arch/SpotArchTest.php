<?php

declare(strict_types=1);

use App\Models\BaseModel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/*
|--------------------------------------------------------------------------
| Spot sub-domain architecture rules
|--------------------------------------------------------------------------
|
| Scoped to `He4rt\FakeBinance\Spot`, não ao módulo inteiro: um `toExtend`/
| `toImplement` module-wide quebraria em classes de outros sub-domínios
| (Ledger, HMAC signing).
|
*/

arch('Spot enums are backed enums implementing HasLabel/HasColor/HasDescription')
    ->expect('He4rt\FakeBinance\Spot\Enums')
    ->toBeEnums()
    ->toImplement([HasLabel::class, HasDescription::class, HasColor::class]);

arch('Spot models extend the shared BaseModel')
    ->expect('He4rt\FakeBinance\Spot\Models')
    ->toExtend(BaseModel::class);
