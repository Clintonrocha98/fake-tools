<?php

declare(strict_types=1);

use He4rt\FakeBinance\Support\BinanceLog;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Fake Binance module architecture rules
|--------------------------------------------------------------------------
|
| Regras de convenção válidas para todo o módulo fake-binance (domínio): strict
| types, sem stdClass e nunca dependência da camada de apresentação. Regras
| de um sub-domínio específico (ex.: models do ledger) vivem no Arch test
| do próprio sub-domínio.
|
*/

arch('the fake-binance domain module declares strict types')
    ->expect('He4rt\FakeBinance')
    ->toUseStrictTypes();

arch('domain code never produces a stdClass')
    ->expect('He4rt\FakeBinance')
    ->not->toUse('stdClass');

arch('domain modules do not depend on the presentation layer')
    ->expect('He4rt\FakeBinance')
    ->not->toUse('He4rt\PanelAdmin');

// O namespace do módulo mapeia só para src/; os seeders têm PSR-4 próprio e
// precisam entrar por nome, senão ficam fora do scan — foi por aí que um
// seeder logando no facade escapou para o emergency logger.
arch('logs saem pelo canal dedicado via BinanceLog, nunca pelo facade Log')
    ->expect(['He4rt\FakeBinance', 'He4rt\FakeBinance\Database\Seeders'])
    ->not->toUse(Log::class)
    ->ignoring(BinanceLog::class);
