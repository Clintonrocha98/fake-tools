<?php

declare(strict_types=1);

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
