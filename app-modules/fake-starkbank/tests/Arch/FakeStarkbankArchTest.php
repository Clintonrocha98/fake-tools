<?php

declare(strict_types=1);

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;

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

arch('os DTOs do webhook são value objects imutáveis')
    ->expect('He4rt\FakeStarkbank\Webhook\DTOs')
    ->toBeFinal()
    ->toBeReadonly();

/*
 * Os enums do webhook são vocabulário que o painel de cenários vai renderizar
 * (badge de subscription, badge de log type na fila de emissões) — os contratos
 * do Filament entram junto com o enum, nunca numa passada depois.
 */
arch('os enums do webhook implementam os contratos que o painel lê')
    ->expect('He4rt\FakeStarkbank\Webhook\Enums')
    ->toBeEnums()
    ->toImplement([HasColor::class, HasDescription::class, HasIcon::class, HasLabel::class]);

arch('os enums da invoice implementam os contratos que o painel lê')
    ->expect('He4rt\FakeStarkbank\Invoice\Enums')
    ->toBeEnums()
    ->toImplement([HasColor::class, HasDescription::class, HasIcon::class, HasLabel::class]);

arch('os DTOs da invoice são value objects imutáveis')
    ->expect('He4rt\FakeStarkbank\Invoice\DTOs')
    ->toBeFinal()
    ->toBeReadonly();

/*
 * As Actions da invoice são o único lugar onde uma transição de estado
 * acontece: um controller que gravasse status direto pularia o log de negócio e
 * a emissão de webhook que acompanham cada transição.
 */
arch('os controllers da invoice não conhecem o model, só as Actions')
    ->expect('He4rt\FakeStarkbank\Invoice\Http\Controllers')
    ->not->toUse(Invoice::class);
