<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use Illuminate\Support\Facades\Route;

uses(SignsRequests::class);

/*
|--------------------------------------------------------------------------
| middleware fake-starkbank.signed
|--------------------------------------------------------------------------
|
| Rotas de teste ad-hoc, registradas sob o grupo `api` (a superfície real de
| roteamento do fake), para exercitar o mecanismo sem depender de um endpoint
| de negócio: um GET assinado, um POST com body assinado (o body ENTRA na
| mensagem) e uma rota pública, que prova que o split assinado/público existe.
| O par de chaves é gerado em runtime a cada teste.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();

    Route::middleware(['api', 'fake-starkbank.signed'])->get('/v2/probe', fn () => response()->json(['ok' => true]));
    Route::middleware(['api', 'fake-starkbank.signed'])->post('/v2/probe', fn () => response()->json(['ok' => true]));
    Route::middleware('api')->get('/v2/public-probe', fn () => response()->json(['ok' => true]));
});

it('deixa passar uma rota pública sem nenhum header de assinatura', function (): void {
    $this->getSigned('/v2/public-probe')
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('aceita um GET assinado com o par configurado', function (): void {
    $this->getSigned('/v2/probe', $this->signedHeaders())
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('aceita um POST cuja assinatura cobre o body exato da wire', function (): void {
    $payload = ['invoices' => []];

    $this->postJson('/v2/probe', $payload, $this->signedHeaders((string) json_encode($payload)))
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('recusa um POST assinado sobre um body diferente do que chegou', function (): void {
    $headers = $this->signedHeaders((string) json_encode(['invoices' => []]));

    $this->postJson('/v2/probe', ['invoices' => [['amount' => 1]]], $headers)
        ->assertStatus(401)
        ->assertExactJson([
            'errors' => [
                ['code' => 'invalidSignature', 'message' => 'Invalid access signature'],
            ],
        ]);
});

it('aceita um GET com query string, porque a query não entra na mensagem assinada', function (): void {
    // A assinatura é sobre `accessId:accessTime:body` — o `?cursor=` viaja na
    // URL e nunca na mensagem, exatamente como ListWorkspacesRequest o envia.
    $this->getSigned('/v2/probe?cursor='.rawurlencode('eyJwYWdlIjoyfQ=='), $this->signedHeaders())
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('recusa com 400 invalidRequest quando falta um dos três headers de assinatura', function (string $ausente): void {
    $headers = $this->signedHeaders();
    unset($headers[$ausente]);

    $this->getSigned('/v2/probe', $headers)
        ->assertStatus(400)
        ->assertExactJson([
            'errors' => [
                ['code' => 'invalidRequest', 'message' => 'Missing Access-Id, Access-Time or Access-Signature header'],
            ],
        ]);
})->with(['Access-Id', 'Access-Time', 'Access-Signature']);

it('recusa com 401 invalidAccessId quando o Access-Id não é o cliente configurado', function (): void {
    $this->getSigned('/v2/probe', $this->signedHeaders(accessId: 'project/0000000000000000'))
        ->assertStatus(401)
        ->assertExactJson([
            'errors' => [
                ['code' => 'invalidAccessId', 'message' => 'Invalid access id'],
            ],
        ]);
});

it('recusa com 401 expiredAccessTime quando o Access-Time está fora da janela', function (): void {
    $this->getSigned('/v2/probe', $this->signedHeaders(accessTime: now()->getTimestamp() - 3_600))
        ->assertStatus(401)
        ->assertExactJson([
            'errors' => [
                ['code' => 'expiredAccessTime', 'message' => 'Expired access time'],
            ],
        ]);
});

it('recusa com 401 expiredAccessTime um Access-Time no futuro além da janela', function (): void {
    $this->getSigned('/v2/probe', $this->signedHeaders(accessTime: now()->getTimestamp() + 3_600))
        ->assertStatus(401)
        ->assertExactJson([
            'errors' => [
                ['code' => 'expiredAccessTime', 'message' => 'Expired access time'],
            ],
        ]);
});

it('recusa com 401 expiredAccessTime um Access-Time presente mas não numérico', function (): void {
    $headers = $this->signedHeaders();
    $headers['Access-Time'] = 'ontem';

    $this->getSigned('/v2/probe', $headers)
        ->assertStatus(401)
        ->assertExactJson([
            'errors' => [
                ['code' => 'expiredAccessTime', 'message' => 'Expired access time'],
            ],
        ]);
});

it('aceita um Access-Time velho quando a janela configurada é larga o bastante', function (): void {
    config(['fake-starkbank.recv_window_seconds' => 7_200]);

    $this->getSigned('/v2/probe', $this->signedHeaders(accessTime: now()->getTimestamp() - 3_600))
        ->assertOk();
});

it('recusa com 401 invalidSignature uma assinatura feita com outra chave privada', function (): void {
    // Rotação de credencial do lado do consumidor: a chave pública configurada
    // continua a antiga, e nada assinado com a nova entra.
    $outraChave = $this->generateKeypair();

    $this->getSigned('/v2/probe', $this->signedHeaders(privateKey: $outraChave))
        ->assertStatus(401)
        ->assertExactJson([
            'errors' => [
                ['code' => 'invalidSignature', 'message' => 'Invalid access signature'],
            ],
        ]);
});

it('recusa com 401 invalidSignature uma assinatura assinada sobre outro Access-Time', function (): void {
    $headers = $this->signedHeaders(accessTime: now()->getTimestamp() - 60);
    $headers['Access-Time'] = (string) now()->getTimestamp();

    $this->getSigned('/v2/probe', $headers)
        ->assertStatus(401)
        ->assertExactJson([
            'errors' => [
                ['code' => 'invalidSignature', 'message' => 'Invalid access signature'],
            ],
        ]);
});

it('recusa com 401 invalidSignature um base64 corrompido', function (): void {
    $headers = $this->signedHeaders();
    $headers['Access-Signature'] = 'isto-não-é-base64-válido!!';

    $this->getSigned('/v2/probe', $headers)
        ->assertStatus(401)
        ->assertExactJson([
            'errors' => [
                ['code' => 'invalidSignature', 'message' => 'Invalid access signature'],
            ],
        ]);
});

it('recusa com 401 invalidSignature quando o fake está sem chave pública configurada', function (): void {
    $headers = $this->signedHeaders();

    config([
        'fake-starkbank.client.public_key' => null,
        'fake-starkbank.client.public_key_path' => null,
    ]);

    $this->getSigned('/v2/probe', $headers)
        ->assertStatus(401)
        ->assertExactJson([
            'errors' => [
                ['code' => 'invalidSignature', 'message' => 'Invalid access signature'],
            ],
        ]);
});

it('reporta invalidAccessId, e não invalidSignature, quando o Access-Id é desconhecido E a assinatura é lixo', function (): void {
    // A ordem das checagens é contrato: um Access-Id que não é o cliente
    // configurado nunca chega ao verify().
    $headers = $this->signedHeaders(accessId: 'project/0000000000000000');
    $headers['Access-Signature'] = 'não-assinei-nada';

    $this->getSigned('/v2/probe', $headers)
        ->assertStatus(401)
        ->assertJsonPath('errors.0.code', 'invalidAccessId');
});

it('reporta expiredAccessTime, e não invalidSignature, quando o Access-Time é velho E a assinatura é lixo', function (): void {
    $headers = $this->signedHeaders(accessTime: now()->getTimestamp() - 3_600);
    $headers['Access-Signature'] = 'não-assinei-nada';

    $this->getSigned('/v2/probe', $headers)
        ->assertStatus(401)
        ->assertJsonPath('errors.0.code', 'expiredAccessTime');
});
