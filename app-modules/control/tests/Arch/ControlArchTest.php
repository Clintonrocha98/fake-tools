<?php

declare(strict_types=1);

use He4rt\Control\Feed\Casts\AsControlEventContext;
use He4rt\Control\Feed\Models\ControlEvent;
use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Regras de arquitetura do módulo control
|--------------------------------------------------------------------------
|
| `control` é APRESENTAÇÃO no sentido do guideline de arquitetura: importa as
| Actions dos dois fakes e nunca o contrário. O precedente é o `panel-admin`, e
| a decisão do mapa da malha PIX — os fakes não se conhecem, sem módulo
| Scenarios compartilhado — fica intacta, porque quem enxerga os dois continua
| sendo apresentação.
|
*/

arch('o módulo control declara strict types')
    ->expect('He4rt\Control')
    ->toUseStrictTypes();

arch('o módulo control nunca produz um stdClass')
    ->expect('He4rt\Control')
    ->not->toUse('stdClass');

arch('o control enxerga os dois fakes porque é apresentação')
    ->expect('He4rt\Control')
    ->toUse(['He4rt\FakeBinance', 'He4rt\FakeStarkbank']);

arch('o fake-binance nunca depende do plano de controle')
    ->expect('He4rt\FakeBinance')
    ->not->toUse('He4rt\Control');

arch('o fake-starkbank nunca depende do plano de controle')
    ->expect('He4rt\FakeStarkbank')
    ->not->toUse('He4rt\Control');

arch('os DTOs do control são value objects imutáveis')
    ->expect('He4rt\Control\Feed\DTOs')
    ->toBeFinal()
    ->toBeReadonly();

arch('os DTOs do snapshot são value objects imutáveis')
    ->expect('He4rt\Control\State\DTOs')
    ->toBeFinal()
    ->toBeReadonly();

/*
 * Uma Action é uma operação só, chamada por um ponto de entrada canônico —
 * `handle()` ou `__invoke()`. Uma classe de `Actions/` que só expõe verbos
 * próprios virou Service.
 */
test('toda Action do módulo é final e tem ponto de entrada canônico', function (): void {
    $infratores = [];

    foreach (Finder::create()->files()->name('*.php')->in(dirname(__DIR__, 2).'/src') as $arquivo) {
        $caminho = str_replace('\\', '/', $arquivo->getRelativePathname());

        if (!str_contains($caminho, '/Actions/')) {
            continue;
        }

        $classe = 'He4rt\\Control\\'.str_replace('/', '\\', mb_substr($caminho, 0, -4));
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
 * O contexto do evento é polimórfico por natureza — cada linha traz as chaves
 * que fazem sentido para ela. Isso é razão para o VO tratar a fronteira uma vez
 * só, nunca para abrir exceção no gate global de casts soltos.
 */
test('o contexto do evento é lido por um cast tipado', function (): void {
    expect(new ControlEvent()->getCasts())->toHaveKey('context')
        ->and(new ControlEvent()->getCasts()['context'])->toBe(AsControlEventContext::class);
});
