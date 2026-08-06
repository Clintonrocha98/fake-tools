<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Brcode\Actions\DecodeBrcode;
use He4rt\FakeStarkbank\Brcode\Exceptions\MalformedBrcodeException;
use He4rt\FakeStarkbank\Tests\Support\BuildsBrcodes;

uses(BuildsBrcodes::class);

/*
|--------------------------------------------------------------------------
| Decodificação do BR Code EMV
|--------------------------------------------------------------------------
|
| O elo que dá valor aos guards do consumidor: o valor e a chave saem dos BYTES
| do código, e um payload que não fecha é recusado antes de virar preview.
|
*/

it('extrai a chave PIX, o valor em centavos e o nome do BR Code estático', function (): void {
    $decodificado = resolve(DecodeBrcode::class)->handle($this->staticBrcode(amount: '250.00'));

    expect($decodificado->pixKey)->toBe('funding@fake-binance.dev')
        ->and($decodificado->amountCentavos)->toBe(25_000)
        ->and($decodificado->receiverName)->toBe('Fake Binance')
        ->and($decodificado->city)->toBe('Sao Paulo')
        ->and($decodificado->allowsAmountChange())->toBeFalse();
});

it('converte o campo 54 por aritmética de string, sem perder centavo', function (string $wire, int $centavos): void {
    expect(resolve(DecodeBrcode::class)->handle($this->staticBrcode(amount: $wire))->amountCentavos)->toBe($centavos);
})->with([
    'centavo único' => ['0.01', 1],
    'centavos quebrados' => ['250.05', 25_005],
    'uma casa decimal' => ['12.5', 1_250],
    'inteiro sem ponto' => ['300', 30_000],
    'valor grande' => ['1234567.89', 123_456_789],
]);

it('marca o BR Code dinâmico com valor ausente, e não com valor zero', function (): void {
    // Zero seria "cobrança de zero real"; ausente é "quem paga escolhe" — e é
    // essa distinção que vira o allowChange do preview.
    $decodificado = resolve(DecodeBrcode::class)->handle($this->dynamicBrcode());

    expect($decodificado->amountCentavos)->toBeNull()
        ->and($decodificado->allowsAmountChange())->toBeTrue();
});

it('aceita o GUI do arranjo PIX em qualquer caixa', function (): void {
    $brcode = $this->brcodeFromFields([
        ['00', '01'],
        ['26', $this->tlvField('00', 'BR.GOV.BCB.PIX').$this->tlvField('01', 'funding@fake-binance.dev')],
        ['54', '250.00'],
    ]);

    expect(resolve(DecodeBrcode::class)->handle($brcode)->pixKey)->toBe('funding@fake-binance.dev');
});

it('encontra o arranjo PIX fora da tag 26, na faixa que a especificação reserva', function (): void {
    $brcode = $this->brcodeFromFields([
        ['00', '01'],
        ['26', $this->tlvField('00', 'com.outro.arranjo').$this->tlvField('01', 'xxxx')],
        ['27', $this->tlvField('00', 'br.gov.bcb.pix').$this->tlvField('01', 'funding@fake-binance.dev')],
        ['54', '250.00'],
    ]);

    expect(resolve(DecodeBrcode::class)->handle($brcode)->pixKey)->toBe('funding@fake-binance.dev');
});

it('recusa um código adulterado depois de emitido, que é o que o CRC existe para pegar', function (): void {
    $adulterado = $this->tamperedBrcode($this->staticBrcode(amount: '250.00'));

    expect(fn () => resolve(DecodeBrcode::class)->handle($adulterado))
        ->toThrow(MalformedBrcodeException::class, 'Brcode CRC16 does not match');
});

it('recusa o que não é EMV', function (string $brcode): void {
    expect(fn () => resolve(DecodeBrcode::class)->handle($brcode))
        ->toThrow(MalformedBrcodeException::class);
})->with([
    'string vazia' => [''],
    'texto solto' => ['nao-e-um-brcode'],
    'header truncado' => ['000201261'],
    'comprimento além do payload' => ['0099abc'],
]);

it('recusa um payload bem formado sem o arranjo br.gov.bcb.pix', function (): void {
    expect(fn () => resolve(DecodeBrcode::class)->handle($this->brcodeWithoutPixKey()))
        ->toThrow(MalformedBrcodeException::class, 'no br.gov.bcb.pix merchant account information');
});

it('recusa um indicador de formato que não é 01', function (): void {
    $brcode = $this->brcodeFromFields([
        ['00', '02'],
        ['26', $this->tlvField('00', 'br.gov.bcb.pix').$this->tlvField('01', 'funding@fake-binance.dev')],
    ]);

    expect(fn () => resolve(DecodeBrcode::class)->handle($brcode))
        ->toThrow(MalformedBrcodeException::class, 'Unsupported payload format indicator: 02');
});

it('recusa um campo 54 que não é decimal', function (): void {
    expect(fn () => resolve(DecodeBrcode::class)->handle($this->staticBrcode(amount: 'muito')))
        ->toThrow(MalformedBrcodeException::class, 'not a decimal value');
});

it('recusa um payload cujo CRC não é o último campo', function (): void {
    // Sem esta guarda, um `6304` plantado no meio do texto passaria por
    // verificação e o resto do payload viajaria sem conferência nenhuma.
    $brcode = $this->staticBrcode().'5802BR';

    expect(fn () => resolve(DecodeBrcode::class)->handle($brcode))
        ->toThrow(MalformedBrcodeException::class);
});
