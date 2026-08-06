<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Fake Starkbank module architecture rules
|--------------------------------------------------------------------------
|
| Regras de convenção válidas para todo o módulo fake-starkbank (domínio):
| strict types, sem stdClass e nunca dependência da camada de apresentação.
| Regras de um sub-domínio específico vivem no Arch test do próprio
| sub-domínio.
|
*/

arch('o módulo de domínio fake-starkbank declara strict types')
    ->expect('He4rt\FakeStarkbank')
    ->toUseStrictTypes();

arch('o código de domínio nunca produz um stdClass')
    ->expect('He4rt\FakeStarkbank')
    ->not->toUse('stdClass');

arch('módulos de domínio não dependem da camada de apresentação')
    ->expect('He4rt\FakeStarkbank')
    ->not->toUse('He4rt\PanelAdmin');

arch('um fake nunca consulta o outro em runtime')
    ->expect('He4rt\FakeStarkbank')
    ->not->toUse('He4rt\FakeBinance');
