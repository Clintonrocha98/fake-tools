<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| middleware fake-starkbank.request-log
|--------------------------------------------------------------------------
|
| Rotas probe ad-hoc sob o grupo `api`, como em VerifiesSignedRequestTest. O
| canal `starkbank` já chega aqui capturado em memória pelo TestCase base — os
| registros saem de fakeLogRecords('starkbank'), o que também prova que o
| middleware loga no canal dedicado, não no default.
|
*/

beforeEach(function (): void {
    Route::middleware(['fake-starkbank.request-log', 'api'])->get('/v2/probe-lifecycle', fn () => response()->json(['ok' => true]));
    Route::middleware(['fake-starkbank.request-log', 'api'])->post('/v2/probe-lifecycle', fn () => response()->json(['ok' => true], 201));
});

it('loga entrada e saída no canal starkbank, amarradas pelo mesmo request_id do header X-Fake-Request-Id', function (): void {
    $response = $this->getJson('/v2/probe-lifecycle?cursor=abc', [
        'Access-Id' => 'project/6341320293482496',
        'Access-Time' => '1712345678',
        'Access-Signature' => 'assinatura-que-nunca-vaza',
    ]);

    $response->assertOk();

    $requestId = $response->headers->get('X-Fake-Request-Id');

    expect($requestId)->toBeString()->not->toBeEmpty();

    $records = fakeLogRecords('starkbank');

    expect($records)->toHaveCount(2);

    [$entrada, $saida] = $records;

    expect($entrada->message)->toContain('request entrou')
        ->and($entrada->context['method'])->toBe('GET')
        ->and($entrada->context['path'])->toBe('/v2/probe-lifecycle')
        ->and($entrada->context['query'])->toBe(['cursor' => 'abc'])
        ->and($entrada->context['access_id'])->toBe('project/6341320293482496')
        ->and($entrada->context['access_time'])->toBe('1712345678')
        ->and($entrada->extra['request_id'])->toBe($requestId);

    expect($saida->message)->toContain('response saiu')
        ->and($saida->context['status'])->toBe(200)
        ->and($saida->context['body'])->toContain('"ok":true')
        ->and($saida->context['duration_ms'])->toBeFloat()
        ->and($saida->extra['request_id'])->toBe($requestId);
});

it('nunca loga a Access-Signature', function (): void {
    $this->getJson('/v2/probe-lifecycle', [
        'Access-Id' => 'project/6341320293482496',
        'Access-Time' => '1712345678',
        'Access-Signature' => 'assinatura-que-nunca-vaza',
    ])->assertOk();

    [$entrada, $saida] = fakeLogRecords('starkbank');

    expect(json_encode($entrada->context))->not->toContain('assinatura-que-nunca-vaza')
        ->and(json_encode($saida->context))->not->toContain('assinatura-que-nunca-vaza');
});

it('registra o body do POST na entrada como chegou na wire', function (): void {
    $this->postJson('/v2/probe-lifecycle', ['invoices' => [['amount' => 5_000]]])
        ->assertCreated();

    [$entrada, $saida] = fakeLogRecords('starkbank');

    expect($entrada->context['body'])->toContain('"amount":5000')
        ->and($saida->context['status'])->toBe(201);
});
