<?php

declare(strict_types=1);

use He4rt\FakeBinance\Tests\Support\SignsRequests;
use Illuminate\Support\Facades\Route;

uses(SignsRequests::class);

/*
|--------------------------------------------------------------------------
| fake-binance.signed middleware
|--------------------------------------------------------------------------
|
| Rotas de teste ad-hoc, registradas sob o grupo `api` (a superfície real de
| roteamento do fake) — os endpoints reais chegam nos tickets seguintes. Uma
| rota "spot/wallet" (fora de sapi/v{n}/fiat/) e uma rota "fiat"
| (sapi/v1/fiat/*), ambas atrás de `fake-binance.signed`, exercitam a escolha de
| envelope pela família do path; uma rota pública, sem `fake-binance.signed`,
| prova que o split assinado/público realmente existe.
|
*/

beforeEach(function (): void {
    $this->configureFakeBinanceCredentials();

    Route::middleware(['api', 'fake-binance.signed'])->get('/api/v3/account', fn () => response()->json(['ok' => true]));
    Route::middleware(['api', 'fake-binance.signed'])->post('/sapi/v1/fiat/deposit', fn () => response()->json(['ok' => true]));
    Route::middleware('api')->get('/api/v3/ticker/price', fn () => response()->json(['ok' => true]));
});

it('lets a public route through without a signature', function (): void {
    $this->getJson('/api/v3/ticker/price')
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('accepts a request signed with the configured secret', function (): void {
    $this->getJson($this->signedUri('/api/v3/account'), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('accepts a request with a body outside the signature, exactly like the monolith connector', function (): void {
    $uri = $this->signedUri('/sapi/v1/fiat/deposit', ['orderNo' => 'abc123']);

    $this->postJson($uri, ['unsigned' => 'body-field-not-part-of-the-signature'], $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('rejects a wrong signature with -1022 in the spot/wallet envelope', function (): void {
    $query = $this->signedQuery();
    $query['signature'] = 'not-the-real-signature';

    $this->getJson('/api/v3/account?'.http_build_query($query), $this->apiKeyHeader())
        ->assertStatus(400)
        ->assertExactJson([
            'code' => -1_022,
            'msg' => 'Signature for this request is not valid.',
        ]);
});

it('rejects a wrong signature with -1022 in the fiat envelope', function (): void {
    $query = $this->signedQuery();
    $query['signature'] = 'not-the-real-signature';

    $this->postJson('/sapi/v1/fiat/deposit?'.http_build_query($query), [], $this->apiKeyHeader())
        ->assertStatus(400)
        ->assertExactJson([
            'code' => '-1022',
            'message' => 'Signature for this request is not valid.',
            'success' => false,
            'data' => null,
        ]);
});

it('rejects a timestamp outside the recvWindow with -1021', function (): void {
    $uri = $this->signedUri('/api/v3/account', timestamp: now()->getTimestampMs() - 10_000);

    $this->getJson($uri, $this->apiKeyHeader())
        ->assertStatus(400)
        ->assertExactJson([
            'code' => -1_021,
            'msg' => 'Timestamp for this request is outside of the recvWindow.',
        ]);
});

it('accepts a timestamp within a custom, smaller recvWindow', function (): void {
    $uri = $this->signedUri('/api/v3/account', timestamp: now()->getTimestampMs() - 500, recvWindow: 1_000);

    $this->getJson($uri, $this->apiKeyHeader())->assertOk();
});

it('rejects a recvWindow above the 60000 ceiling with -1021', function (): void {
    $uri = $this->signedUri('/api/v3/account', recvWindow: 600_000);

    $this->getJson($uri, $this->apiKeyHeader())
        ->assertStatus(400)
        ->assertExactJson([
            'code' => -1_021,
            'msg' => 'Timestamp for this request is outside of the recvWindow.',
        ]);
});

it('rejects a negative recvWindow with -1021 instead of silently accepting it', function (): void {
    $query = $this->signedQuery(['recvWindow' => -1], recvWindow: null);

    $this->getJson('/api/v3/account?'.http_build_query($query), $this->apiKeyHeader())
        ->assertStatus(400)
        ->assertExactJson([
            'code' => -1_021,
            'msg' => 'Timestamp for this request is outside of the recvWindow.',
        ]);
});

it('rejects a request missing the timestamp with -1102', function (): void {
    $query = ['signature' => 'irrelevant'];

    $this->getJson('/api/v3/account?'.http_build_query($query), $this->apiKeyHeader())
        ->assertStatus(400)
        ->assertExactJson([
            'code' => -1_102,
            'msg' => 'A mandatory parameter was not sent, was empty/null, or malformed.',
        ]);
});

it('rejects a request missing the signature with -1102', function (): void {
    $query = ['timestamp' => now()->getTimestampMs()];

    $this->getJson('/api/v3/account?'.http_build_query($query), $this->apiKeyHeader())
        ->assertStatus(400)
        ->assertExactJson([
            'code' => -1_102,
            'msg' => 'A mandatory parameter was not sent, was empty/null, or malformed.',
        ]);
});

it('reports -1022 rather than -1021 when both the timestamp is stale and the signature is wrong', function (): void {
    $query = $this->signedQuery(timestamp: now()->getTimestampMs() - 10_000);
    $query['signature'] = 'not-the-real-signature';

    $this->getJson('/api/v3/account?'.http_build_query($query), $this->apiKeyHeader())
        ->assertStatus(400)
        ->assertExactJson([
            'code' => -1_022,
            'msg' => 'Signature for this request is not valid.',
        ]);
});

it('rejects a request missing the X-MBX-APIKEY header with -2014', function (): void {
    $this->getJson($this->signedUri('/api/v3/account'))
        ->assertStatus(401)
        ->assertExactJson([
            'code' => -2_014,
            'msg' => 'API-key format invalid.',
        ]);
});

it('rejects a request with a wrong X-MBX-APIKEY value with -2015', function (): void {
    $this->getJson($this->signedUri('/api/v3/account'), $this->apiKeyHeader('a-key-that-was-never-configured'))
        ->assertStatus(401)
        ->assertExactJson([
            'code' => -2_015,
            'msg' => 'Invalid API-key, IP, or permissions for action.',
        ]);
});

it('rejects a fiat request missing the X-MBX-APIKEY header with -2014 in the fiat envelope', function (): void {
    $this->postJson($this->signedUri('/sapi/v1/fiat/deposit'), [])
        ->assertStatus(401)
        ->assertExactJson([
            'code' => '-2014',
            'message' => 'API-key format invalid.',
            'success' => false,
            'data' => null,
        ]);
});

it('rejects a fiat request with a timestamp outside the recvWindow with -1021 in the fiat envelope', function (): void {
    $uri = $this->signedUri('/sapi/v1/fiat/deposit', timestamp: now()->getTimestampMs() - 10_000);

    $this->postJson($uri, [], $this->apiKeyHeader())
        ->assertStatus(400)
        ->assertExactJson([
            'code' => '-1021',
            'message' => 'Timestamp for this request is outside of the recvWindow.',
            'success' => false,
            'data' => null,
        ]);
});

it('rejects a fiat request missing the timestamp with -1102 in the fiat envelope', function (): void {
    $query = ['signature' => 'irrelevant'];

    $this->postJson('/sapi/v1/fiat/deposit?'.http_build_query($query), [], $this->apiKeyHeader())
        ->assertStatus(400)
        ->assertExactJson([
            'code' => '-1102',
            'message' => 'A mandatory parameter was not sent, was empty/null, or malformed.',
            'success' => false,
            'data' => null,
        ]);
});
