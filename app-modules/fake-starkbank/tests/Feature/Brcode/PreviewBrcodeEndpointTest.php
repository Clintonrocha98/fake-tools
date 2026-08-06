<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Database\Seeders\DictEntrySeeder;
use He4rt\FakeStarkbank\Tests\Support\BuildsBrcodes;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;

uses(SignsRequests::class, BuildsBrcodes::class);

/*
|--------------------------------------------------------------------------
| GET /v2/brcode-preview?brcodes={brcode}
|--------------------------------------------------------------------------
|
| O insumo dos dois guards do SendConversionFunding: o valor sai dos bytes do
| EMV, o recebedor sai do registro DICT. Nada aqui vem de config — servir os
| dois por configuração faria os guards passarem sempre.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->seed(DictEntrySeeder::class);
});

it('resolve o recebedor no DICT e o valor no campo 54', function (): void {
    $response = $this->getSigned(
        '/v2/brcode-preview?brcodes='.rawurlencode($this->staticBrcode(amount: '250.00')),
        $this->signedHeaders(),
    );

    $response->assertOk()
        ->assertJsonCount(1, 'previews')
        ->assertJsonPath('previews.0.status', 'active')
        ->assertJsonPath('previews.0.name', 'Fake Binance')
        ->assertJsonPath('previews.0.taxId', '20.018.183/0001-80')
        ->assertJsonPath('previews.0.bankCode', '20018183')
        ->assertJsonPath('previews.0.accountType', 'checking')
        ->assertJsonPath('previews.0.amount', 25_000)
        ->assertJsonPath('previews.0.nominalAmount', 25_000)
        ->assertJsonPath('previews.0.allowChange', false);
});

it('acompanha o valor do código, e não uma constante', function (): void {
    // O guard de valor do consumidor compara o preview com a Conversion; um
    // valor de config faria esse guard passar sempre ou falhar sempre.
    $response = $this->getSigned(
        '/v2/brcode-preview?brcodes='.rawurlencode($this->staticBrcode(amount: '1337.42')),
        $this->signedHeaders(),
    );

    $response->assertOk()->assertJsonPath('previews.0.amount', 133_742);
});

it('serve taxId e bankCode vazios quando a chave não está registrada, sem recusar', function (): void {
    // É este o caminho que exercita FundingNotSendable::destinationUnverifiable:
    // o fake responde 200 e quem recusa é o consumidor.
    $response = $this->getSigned(
        '/v2/brcode-preview?brcodes='.rawurlencode($this->staticBrcode(pixKey: 'ninguem@brd.digital', name: 'Quem Sabe')),
        $this->signedHeaders(),
    );

    $response->assertOk()
        ->assertJsonPath('previews.0.taxId', '')
        ->assertJsonPath('previews.0.bankCode', '')
        ->assertJsonPath('previews.0.accountType', '')
        // Sem entry no DICT, o nome cai no texto livre do campo 59 do EMV.
        ->assertJsonPath('previews.0.name', 'Quem Sabe')
        ->assertJsonPath('previews.0.amount', 25_000);
});

it('anuncia allowChange no BR Code dinâmico, com valor zero', function (): void {
    $response = $this->getSigned(
        '/v2/brcode-preview?brcodes='.rawurlencode($this->dynamicBrcode()),
        $this->signedHeaders(),
    );

    $response->assertOk()
        ->assertJsonPath('previews.0.allowChange', true)
        ->assertJsonPath('previews.0.amount', 0)
        ->assertJsonPath('previews.0.nominalAmount', 0);
});

it('recusa um código adulterado com invalidBrcode', function (): void {
    $response = $this->getSigned(
        '/v2/brcode-preview?brcodes='.rawurlencode($this->tamperedBrcode($this->staticBrcode())),
        $this->signedHeaders(),
    );

    $response->assertStatus(400)
        ->assertHeader('content-type', 'application/json')
        ->assertJsonPath('errors.0.code', 'invalidBrcode');

    expect((string) $response->json('errors.0.message'))->toContain('CRC16');
});

it('recusa um payload que não é EMV com invalidBrcode', function (): void {
    $this->getSigned('/v2/brcode-preview?brcodes=nao-e-um-brcode', $this->signedHeaders())
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidBrcode');
});

it('recusa a query sem o parâmetro brcodes', function (): void {
    $this->getSigned('/v2/brcode-preview', $this->signedHeaders())
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest')
        ->assertJsonPath('errors.0.message', 'Missing parameters in query: brcodes');
});

it('trata a query inteira como UM brcode, mesmo com vírgula no meio', function (): void {
    // O provedor aceita lista; o consumidor manda sempre um. Fatiar por vírgula
    // quebraria um copia-e-cola que contivesse uma.
    $this->getSigned(
        '/v2/brcode-preview?brcodes='.rawurlencode($this->staticBrcode(name: 'Fake, Binance')),
        $this->signedHeaders(),
    )
        ->assertOk()
        ->assertJsonCount(1, 'previews');
});

it('exige assinatura como toda rota do fake', function (): void {
    $this->getSigned('/v2/brcode-preview?brcodes='.rawurlencode($this->staticBrcode()))
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest');
});

it('recusa a assinatura de um cliente alheio', function (): void {
    $this->getSigned(
        '/v2/brcode-preview?brcodes='.rawurlencode($this->staticBrcode()),
        $this->signedHeaders(privateKey: $this->generateKeypair()),
    )
        ->assertStatus(401)
        ->assertJsonPath('errors.0.code', 'invalidSignature');
});
