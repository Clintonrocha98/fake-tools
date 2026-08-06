<?php

declare(strict_types=1);

use He4rt\FakeBinance\Fiat\Actions\DelayFiatBrcode;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Tests\Support\EmvDecoder;
use He4rt\FakeBinance\Tests\Support\SignsRequests;

uses(SignsRequests::class);

beforeEach(function (): void {
    $this->configureFakeBinanceCredentials();

    config([
        'fake-binance-fiat.pix_key' => 'funding@fake-binance.dev',
        'fake-binance-fiat.merchant_name' => 'Fake Binance',
        'fake-binance-fiat.merchant_city' => 'Sao Paulo',
    ]);
});

/*
|--------------------------------------------------------------------------
| O pixcode servido é um BR Code EMV pagável
|--------------------------------------------------------------------------
|
| O consumidor não só lê o `pixcode` de get-order-detail: ele o manda para o
| preview do StarkBank antes de pagar. Estes cenários fecham o elo — o que sai
| na wire decodifica, tem CRC válido e carrega o valor exato da FiatOrder.
|
*/

it('serve no pixcode um BR Code que decodifica e fecha o CRC', function (): void {
    $abertura = $this->postJson(
        $this->signedUri('/sapi/v1/fiat/deposit'),
        ['currency' => 'BRL', 'apiPaymentMethod' => 'Pix', 'amount' => '4924.50'],
        $this->apiKeyHeader(),
    )->assertOk();

    $orderNo = (string) $abertura->json('data.orderId');

    $detalhe = $this->getJson(
        $this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $orderNo]),
        $this->apiKeyHeader(),
    )->assertOk();

    $pixcode = (string) $detalhe->json('data.pixcode');
    $mai = EmvDecoder::value($pixcode, '26');

    expect(EmvDecoder::crcIsValid($pixcode))->toBeTrue()
        ->and(EmvDecoder::value($pixcode, '54'))->toBe('4924.50')
        ->and(EmvDecoder::value($mai, '00'))->toBe('br.gov.bcb.pix')
        ->and(EmvDecoder::value($mai, '01'))->toBe('funding@fake-binance.dev');
});

it('faz o campo 54 refletir o amount de cada depósito, é ele que o guard de valor do consumidor confere', function (string $amount, string $campo54): void {
    $abertura = $this->postJson(
        $this->signedUri('/sapi/v1/fiat/deposit'),
        ['currency' => 'BRL', 'apiPaymentMethod' => 'Pix', 'amount' => $amount],
        $this->apiKeyHeader(),
    )->assertOk();

    $order = FiatOrder::query()->where('order_no', (string) $abertura->json('data.orderId'))->firstOrFail();

    $campoValor = EmvDecoder::value((string) $order->brcode, '54');

    expect($campoValor)->toBe($campo54)
        ->and(bccomp($campoValor, (string) $order->amount, 2))->toBe(0);
})->with([
    'centavos redondos' => ['100.00', '100.00'],
    'centavos quebrados' => ['4924.50', '4924.50'],
    'inteiro sem ponto' => ['7500', '7500.00'],
]);

it('não deixa dois depósitos de valores diferentes compartilharem o mesmo BR Code', function (): void {
    $emite = function (string $amount): string {
        $abertura = $this->postJson(
            $this->signedUri('/sapi/v1/fiat/deposit'),
            ['currency' => 'BRL', 'apiPaymentMethod' => 'Pix', 'amount' => $amount],
            $this->apiKeyHeader(),
        )->assertOk();

        return (string) FiatOrder::query()
            ->where('order_no', (string) $abertura->json('data.orderId'))
            ->firstOrFail()
            ->brcode;
    };

    expect($emite('100.00'))->not->toBe($emite('200.00'));
});

it('mantém o atraso do painel ortogonal: o brcode continua real, só demora a aparecer', function (): void {
    $order = FiatOrder::factory()->create(['amount' => '4924.50']);

    (new DelayFiatBrcode)->handle($order, reads: 1);

    $uri = fn (): string => $this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]);

    $this->getJson($uri(), $this->apiKeyHeader())->assertOk()->assertJson(['data' => ['pixcode' => null]]);

    $pixcode = (string) $this->getJson($uri(), $this->apiKeyHeader())->assertOk()->json('data.pixcode');

    expect(EmvDecoder::crcIsValid($pixcode))->toBeTrue()
        ->and(EmvDecoder::value($pixcode, '54'))->toBe('4924.50');
});
