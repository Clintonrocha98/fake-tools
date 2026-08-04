<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Venue module architecture rules
|--------------------------------------------------------------------------
|
| Regras de convenção do módulo venue (domínio): strict types, sem stdClass,
| e nunca dependência da camada de apresentação — mesmo padrão do
| identity/tests/Arch/IdentityArchTest.php.
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
