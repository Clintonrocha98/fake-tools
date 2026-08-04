<?php

declare(strict_types=1);

use He4rt\Venue\Tests\Contract\Support\AssertsRecordedShape;
use He4rt\Venue\Tests\Support\SignsRequests;

/*
 * Os dois envelopes de erro (spot/wallet `{code: int, msg}` vs fiat `{code: string,
 * message, success, data}`) e os quatro códigos de assinatura que
 * `Brd\IntegrationBinance\Support\BinanceErrorBoundary` traduz para exceções
 * neutras: -1022 (assinatura), -1021 (recvWindow), -2014 (API key ausente),
 * -2015 (API key inválida). O boundary do consumidor lê `(int) $response->json
 * ('code')` e `(string) $response->json('msg')` sem olhar a família do path — o
 * cast para int funciona nos dois envelopes (`-1022` string vira -1022 int), mas
 * `msg` só existe no envelope spot/wallet; o teste final prova essa
 * compatibilidade parcial (o código de erro sempre sobrevive, a mensagem só no
 * dialeto certo) em vez de assumir uma coisa ou outra.
 */

uses(SignsRequests::class, AssertsRecordedShape::class);

beforeEach(fn () => $this->configureVenueCredentials());

it('answers the spot/wallet error envelope shape on a bad signature at a spot/wallet-family endpoint', function (): void {
    $query = $this->signedQuery();
    $query['signature'] = 'not-the-real-signature';

    $response = $this->getJson('/api/v3/account?'.http_build_query($query), $this->apiKeyHeader());

    $response->assertStatus(400);

    $this->assertMatchesRecordedShape($this->loadContractFixture('errors/spot_wallet_error.json'), $response->json());
    expect($response->json('code'))->toBe(-1_022);
});

it('answers the fiat error envelope shape on a bad signature at a fiat-family endpoint', function (): void {
    $query = $this->signedQuery();
    $query['signature'] = 'not-the-real-signature';

    $response = $this->postJson('/sapi/v1/fiat/deposit?'.http_build_query($query), [], $this->apiKeyHeader());

    $response->assertStatus(400);

    $this->assertMatchesRecordedShape($this->loadContractFixture('errors/fiat_error.json'), $response->json());
    expect($response->json('code'))->toBe('-1022');
});

it('answers -1021 for a stale timestamp in both envelope families', function (string $path, string $method): void {
    $uri = $this->signedUri($path, timestamp: now()->getTimestampMs() - 10_000);

    $response = $method === 'GET' ? $this->getJson($uri, $this->apiKeyHeader()) : $this->postJson($uri, [], $this->apiKeyHeader());

    $response->assertStatus(400);

    expect((int) $response->json('code'))->toBe(-1_021);
})->with([
    'spot/wallet family' => ['/api/v3/account', 'GET'],
    'fiat family' => ['/sapi/v1/fiat/deposit', 'POST'],
]);

it('answers -2014 when the X-MBX-APIKEY header is missing in both envelope families', function (string $path, string $method): void {
    $uri = $this->signedUri($path);

    $response = $method === 'GET' ? $this->getJson($uri) : $this->postJson($uri, []);

    $response->assertStatus(401);

    expect((int) $response->json('code'))->toBe(-2_014);
})->with([
    'spot/wallet family' => ['/api/v3/account', 'GET'],
    'fiat family' => ['/sapi/v1/fiat/deposit', 'POST'],
]);

it('answers -2015 when X-MBX-APIKEY does not match the configured key in both envelope families', function (string $path, string $method): void {
    $uri = $this->signedUri($path);
    $wrongKeyHeader = $this->apiKeyHeader('a-key-that-was-never-configured');

    $response = $method === 'GET' ? $this->getJson($uri, $wrongKeyHeader) : $this->postJson($uri, [], $wrongKeyHeader);

    $response->assertStatus(401);

    expect((int) $response->json('code'))->toBe(-2_015);
})->with([
    'spot/wallet family' => ['/api/v3/account', 'GET'],
    'fiat family' => ['/sapi/v1/fiat/deposit', 'POST'],
]);

it('documents the known gap: BinanceErrorBoundary::translate() loses the reason text on a fiat-family signature error', function (): void {
    // GAP RASTREADO (ver ADR-0001, venue): o boundary do consumidor lê SEMPRE
    // `code`/`msg` (vocabulário spot/wallet), mesmo numa chamada fiat.
    // `(int) $body['code']` sobrevive nos dois dialetos — PHP converte a
    // numeric-string ("-1022") para int igual a um int nativo — então o
    // MAPEAMENTO do código para exceção nunca quebra entre famílias. Mas
    // `$body['msg']` só existe no dialeto spot/wallet: um erro de assinatura na
    // perna fiat perde o texto da mensagem (`message`, não `msg`) e o operador
    // lê um `reason` vazio. Este teste PROVA o gap — não o endossa — para que
    // ele fique visível até a correção upstream (ou, se a Binance real de fato
    // sempre responde erro de assinatura no envelope spot/wallet mesmo em paths
    // fiat, até o fake ser corrigido).
    $query = $this->signedQuery();
    $query['signature'] = 'not-the-real-signature';

    $spotWalletResponse = $this->getJson('/api/v3/account?'.http_build_query($query), $this->apiKeyHeader());
    $fiatResponse = $this->postJson('/sapi/v1/fiat/deposit?'.http_build_query($query), [], $this->apiKeyHeader());

    $spotWalletBody = (array) $spotWalletResponse->json();
    $fiatBody = (array) $fiatResponse->json();

    expect((int) $spotWalletBody['code'])->toBe(-1_022)
        ->and((int) $fiatBody['code'])->toBe(-1_022)
        ->and((string) ($spotWalletBody['msg'] ?? ''))->toBe('Signature for this request is not valid.')
        ->and((string) ($fiatBody['msg'] ?? ''))->toBeEmpty(); // `msg` não existe no envelope fiat — o boundary perde o texto, nunca o código.
});
