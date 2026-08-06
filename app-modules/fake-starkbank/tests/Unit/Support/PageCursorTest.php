<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Support\PageCursor;

/*
 * O cursor opaco das listagens. Um valor ilegível NUNCA vira erro: o mesmo
 * parâmetro `after` também recebe uma data ISO-8601, e quem chama decide o que
 * fazer com o null.
 */

it('sobrevive à ida e volta preservando o instante e o id', function (): void {
    $cursor = PageCursor::at(CarbonImmutable::parse('2026-07-09T12:00:00.762238+00:00'), '5155165527080960');

    $decodificado = PageCursor::tryDecode($cursor->encode());

    expect($decodificado)->toBeInstanceOf(PageCursor::class)
        ->and($decodificado?->id)->toBe('5155165527080960')
        ->and($decodificado?->createdAt->format('Y-m-d\TH:i:s.uP'))->toBe('2026-07-09T12:00:00.762238+00:00');
});

it('não decodifica o que não é um cursor desta listagem', function (string $bruto): void {
    expect(PageCursor::tryDecode($bruto))->toBeNull();
})->with([
    'data ISO-8601 do --after' => ['2026-07-09T12:00:00+00:00'],
    'texto solto' => ['nao-e-cursor'],
    'base64 de um JSON sem as keys' => [base64_encode('{"foo":"bar"}')],
    'base64 do que nem é JSON' => [base64_encode('bytes soltos')],
    'vazio' => [''],
]);

it('não carrega padding nem caracteres que precisem de escape na query string', function (): void {
    $encoded = PageCursor::at(CarbonImmutable::now(), '5155165527080960')->encode();

    expect($encoded)->toMatch('/^[A-Za-z0-9\-_]+$/')
        ->and(urlencode($encoded))->toBe($encoded);
});
