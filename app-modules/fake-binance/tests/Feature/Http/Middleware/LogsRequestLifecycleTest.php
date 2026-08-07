<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| middleware fake-binance.request-log
|--------------------------------------------------------------------------
|
| Rotas probe ad-hoc sob o grupo `api`, como em VerifiesSignedRequestTest. O
| canal `binance` já chega aqui capturado em memória pelo TestCase base — os
| registros saem de fakeLogRecords('binance'), o que também prova que o
| middleware loga no canal dedicado, não no default.
|
*/

beforeEach(function (): void {
    Route::middleware(['fake-binance.request-log', 'api'])->get('/probe-lifecycle', fn () => response()->json(['ok' => true]));
    Route::middleware(['fake-binance.request-log', 'api'])->post('/probe-lifecycle', fn () => response()->json(['ok' => true], 201));
});

it('loga entrada e saída no canal binance, amarradas pelo mesmo request_id do header X-Fake-Request-Id', function (): void {
    $response = $this->getJson('/probe-lifecycle?symbol=BTCBRL&timestamp=1712345678901&signature=deadbeef');

    $response->assertOk();

    $requestId = $response->headers->get('X-Fake-Request-Id');

    expect($requestId)->toBeString()->not->toBeEmpty();

    $records = fakeLogRecords('binance');

    expect($records)->toHaveCount(2);

    [$entrada, $saida] = $records;

    expect($entrada->message)->toContain('request entrou')
        ->and($entrada->context['method'])->toBe('GET')
        ->and($entrada->context['path'])->toBe('/probe-lifecycle')
        ->and($entrada->extra['request_id'])->toBe($requestId);

    expect($saida->message)->toContain('response saiu')
        ->and($saida->context['status'])->toBe(200)
        ->and($saida->context['body'])->toContain('"ok":true')
        ->and($saida->context['duration_ms'])->toBeFloat()
        ->and($saida->extra['request_id'])->toBe($requestId);
});

it('redige a signature da query e preserva os parâmetros de negócio', function (): void {
    $this->getJson('/probe-lifecycle?symbol=BTCBRL&timestamp=1712345678901&signature=deadbeef')
        ->assertOk();

    [$entrada] = fakeLogRecords('binance');

    expect($entrada->context['query'])->toBe([
        'symbol' => 'BTCBRL',
        'timestamp' => '1712345678901',
        'signature' => '[redigida]',
    ])->and(json_encode($entrada->context))->not->toContain('deadbeef');
});

it('registra o body do POST na entrada e o status da recusa na saída', function (): void {
    $this->postJson('/probe-lifecycle', ['coin' => 'USDT', 'amount' => '25.00'])
        ->assertCreated();

    [$entrada, $saida] = fakeLogRecords('binance');

    expect($entrada->context['body'])->toContain('"amount":"25.00"')
        ->and($saida->context['status'])->toBe(201);
});
