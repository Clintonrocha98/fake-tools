<?php

declare(strict_types=1);

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Scenarios\Casts\AsPixScenarioPayload;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\FakeStarkbank\Support\Casts\AsWireTags;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Casts\AsWebhookPayload;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Symfony\Component\Finder\Finder;

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

arch('os enums da transfer implementam os contratos que o painel lê')
    ->expect('He4rt\FakeStarkbank\Transfer\Enums')
    ->toBeEnums()
    ->toImplement([HasColor::class, HasDescription::class, HasIcon::class, HasLabel::class]);

arch('os DTOs da transfer são value objects imutáveis')
    ->expect('He4rt\FakeStarkbank\Transfer\DTOs')
    ->toBeFinal()
    ->toBeReadonly();

arch('os controllers da transfer não conhecem o model, só as Actions')
    ->expect('He4rt\FakeStarkbank\Transfer\Http\Controllers')
    ->not->toUse(Transfer::class);

arch('os enums do brcode implementam os contratos que o painel lê')
    ->expect('He4rt\FakeStarkbank\Brcode\Enums')
    ->toBeEnums()
    ->toImplement([HasColor::class, HasDescription::class, HasIcon::class, HasLabel::class]);

arch('os DTOs do brcode são value objects imutáveis')
    ->expect('He4rt\FakeStarkbank\Brcode\DTOs')
    ->toBeFinal()
    ->toBeReadonly();

arch('os controllers do brcode não conhecem o model, só as Actions')
    ->expect('He4rt\FakeStarkbank\Brcode\Http\Controllers')
    ->not->toUse(BrcodePayment::class);

arch('os enums do DICT implementam os contratos que o painel lê')
    ->expect('He4rt\FakeStarkbank\Dict\Enums')
    ->toBeEnums()
    ->toImplement([HasColor::class, HasDescription::class, HasIcon::class, HasLabel::class]);

arch('os DTOs do DICT são value objects imutáveis')
    ->expect('He4rt\FakeStarkbank\Dict\DTOs')
    ->toBeFinal()
    ->toBeReadonly();

arch('os controllers do DICT não conhecem o model, só as Actions')
    ->expect('He4rt\FakeStarkbank\Dict\Http\Controllers')
    ->not->toUse(DictEntry::class);

/*
 * Uma Action é uma operação só, chamada por um ponto de entrada canônico —
 * `handle()` ou `__invoke()`. Uma classe de `Actions/` que só expõe verbos
 * próprios virou Service, e o painel de cenários perde o gancho que ele chama.
 */
test('toda Action do módulo é final e tem ponto de entrada canônico', function (): void {
    $infratores = [];

    foreach (Finder::create()->files()->name('*.php')->in(dirname(__DIR__, 2).'/src') as $arquivo) {
        $caminho = str_replace('\\', '/', $arquivo->getRelativePathname());

        if (!str_contains($caminho, '/Actions/')) {
            continue;
        }

        $classe = 'He4rt\\FakeStarkbank\\'.str_replace('/', '\\', mb_substr($caminho, 0, -4));
        $reflexao = new ReflectionClass($classe);

        if (!$reflexao->hasMethod('handle') && !$reflexao->hasMethod('__invoke')) {
            $infratores[] = $classe.' não expõe handle() nem __invoke()';
        }

        if (!$reflexao->isFinal()) {
            $infratores[] = $classe.' não é final';
        }
    }

    expect($infratores)->toBeEmpty();
});

/*
 * Um cast tipado é o que impede um jsonb de virar `mixed` no PHPStan e uma key
 * mágica no call site. A regra global do repo (`NoLooseArrayCastsTest`) proíbe
 * o cast solto; esta afirma o lado positivo para as colunas jsonb deste módulo.
 */
test('toda coluna jsonb do módulo é lida por um cast tipado', function (string $model, string $coluna, string $cast): void {
    expect(new $model()->getCasts())->toHaveKey($coluna)
        ->and(new $model()->getCasts()[$coluna])->toBe($cast);
})->with([
    'tags da invoice' => [Invoice::class, 'tags', AsWireTags::class],
    'tags do brcode-payment' => [BrcodePayment::class, 'tags', AsWireTags::class],
    'tags da transfer' => [Transfer::class, 'tags', AsWireTags::class],
    'payload da emissão' => [WebhookEmission::class, 'payload', AsWebhookPayload::class],
    'payload do cenário armado' => [ArmedScenario::class, 'payload', AsPixScenarioPayload::class],
]);
