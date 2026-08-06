<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Scenarios\DTOs\PixScenarioPayload;

/*
|--------------------------------------------------------------------------
| PixScenarioPayload
|--------------------------------------------------------------------------
|
| O VO é a única fonte do shape do jsonb `payload`: uma coluna adulterada à mão
| nunca pode virar um campo meio preenchido dentro do plano de execução.
|
*/

it('nasce vazio com os dois campos nulos', function (): void {
    $payload = PixScenarioPayload::empty();

    expect($payload->reason)->toBeNull()
        ->and($payload->extraSeconds)->toBeNull()
        ->and($payload->toArray())->toBeEmpty();
});

it('lê os dois campos que algum desfecho usa', function (): void {
    $payload = PixScenarioPayload::fromArray(['reason' => 'Conta encerrada', 'extraSeconds' => 120]);

    expect($payload->reason)->toBe('Conta encerrada')
        ->and($payload->extraSeconds)->toBe(120);
});

it('descarta as chaves que nenhum desfecho da malha PIX reconhece', function (): void {
    $payload = PixScenarioPayload::fromArray(['fraction' => '0.5', 'rawStatus' => 'BANANA']);

    expect($payload->toArray())->toBeEmpty();
});

it('normaliza motivo vazio para ausente', function (): void {
    expect(PixScenarioPayload::fromArray(['reason' => ''])->reason)->toBeNull();
});

it('recusa um atraso fora da faixa aceitável em vez de gravá-lo', function (mixed $extraSeconds): void {
    // Negativo adiantaria o relógio em vez de atrasá-lo, e um valor absurdo
    // congelaria a invoice sem o operador perceber que foi ele quem a congelou.
    expect(PixScenarioPayload::fromArray(['extraSeconds' => $extraSeconds])->extraSeconds)->toBeNull();
})->with([
    'negativo' => [-1],
    'acima de um dia' => [86_401],
    'não numérico' => ['depois'],
    'nulo' => [null],
]);

it('aceita a numeric-string que a UI produz', function (): void {
    expect(PixScenarioPayload::fromArray(['extraSeconds' => '90'])->extraSeconds)->toBe(90);
});

it('devolve o default quando o operador não informou o campo', function (): void {
    $payload = PixScenarioPayload::empty();

    expect($payload->reasonOr('Recusada pela rede'))->toBe('Recusada pela rede')
        ->and($payload->extraSecondsOr(300))->toBe(300);
});

it('devolve o valor armado no lugar do default quando ele existe', function (): void {
    $payload = new PixScenarioPayload(reason: 'Conta encerrada', extraSeconds: 30);

    expect($payload->reasonOr('Recusada pela rede'))->toBe('Conta encerrada')
        ->and($payload->extraSecondsOr(300))->toBe(30);
});
