<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Venue module architecture rules
|--------------------------------------------------------------------------
|
| Regras de convenção válidas para todo o módulo venue (domínio): strict
| types, sem stdClass e nunca dependência da camada de apresentação. Regras
| de um sub-domínio específico (ex.: models do ledger) vivem no Arch test
| do próprio sub-domínio.
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
