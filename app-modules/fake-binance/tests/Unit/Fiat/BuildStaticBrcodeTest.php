<?php

declare(strict_types=1);

use He4rt\FakeBinance\Fiat\Actions\BuildStaticBrcode;
use He4rt\FakeBinance\Fiat\Support\Crc16;
use He4rt\FakeBinance\Tests\Support\EmvDecoder;

/*
|--------------------------------------------------------------------------
| BuildStaticBrcode — o BR Code EMV estático do depósito
|--------------------------------------------------------------------------
|
| Nada aqui confere o encoder contra ele mesmo: o payload é relido por
| EmvDecoder (TLV por offset, CRC16 por tabela) e o valor do campo 54 é
| comparado com o `amount` de entrada, não com um trecho do próprio encoder.
|
*/

beforeEach(function (): void {
    config([
        'fake-binance-fiat.pix_key' => 'funding@fake-binance.dev',
        'fake-binance-fiat.merchant_name' => 'Fake Binance',
        'fake-binance-fiat.merchant_city' => 'Sao Paulo',
    ]);
});

it('monta os campos do BR Code na ordem que o EMV exige', function (): void {
    $brcode = (new BuildStaticBrcode)->handle('4924.50');

    expect(EmvDecoder::tags($brcode))
        ->toBe(['00', '26', '52', '53', '54', '58', '59', '60', '62', '63']);
});

it('serve as constantes do arranjo PIX que nenhum depósito escolhe', function (): void {
    $brcode = (new BuildStaticBrcode)->handle('4924.50');

    expect(EmvDecoder::value($brcode, '00'))->toBe('01')
        ->and(EmvDecoder::value($brcode, '52'))->toBe('0000')
        ->and(EmvDecoder::value($brcode, '53'))->toBe('986')
        ->and(EmvDecoder::value($brcode, '58'))->toBe('BR')
        ->and(EmvDecoder::tags(EmvDecoder::value($brcode, '62')))->toBe(['05'])
        ->and(EmvDecoder::value(EmvDecoder::value($brcode, '62'), '05'))->toBe('***');
});

it('embute a chave PIX de config no merchant account information, que é o que o outro fake resolve no DICT', function (): void {
    config(['fake-binance-fiat.pix_key' => 'outra-chave@fake-binance.dev']);

    $mai = EmvDecoder::value((new BuildStaticBrcode)->handle('100.00'), '26');

    expect(EmvDecoder::tags($mai))->toBe(['00', '01'])
        ->and(EmvDecoder::value($mai, '00'))->toBe('br.gov.bcb.pix')
        ->and(EmvDecoder::value($mai, '01'))->toBe('outra-chave@fake-binance.dev');
});

it('carrega o valor da ordem no campo 54, sempre com duas casas', function (string $amount, string $campo54): void {
    expect(EmvDecoder::value((new BuildStaticBrcode)->handle($amount), '54'))->toBe($campo54);
})->with([
    'centavos redondos' => ['100.00', '100.00'],
    'centavos quebrados' => ['4924.50', '4924.50'],
    'escala 18 do model' => ['4924.500000000000000000', '4924.50'],
    'inteiro sem ponto' => ['250', '250.00'],
    'centavo solitário' => ['0.01', '0.01'],
    'milhão' => ['1000000.99', '1000000.99'],
]);

it('emite 0.00 em vez de derrubar o depósito quando o amount é ilegível', function (): void {
    expect(EmvDecoder::value((new BuildStaticBrcode)->handle('não é número'), '54'))->toBe('0.00');
});

it('fecha o payload com um CRC16 que um leitor independente aceita', function (): void {
    $brcode = (new BuildStaticBrcode)->handle('4924.50');

    expect(EmvDecoder::crcIsValid($brcode))->toBeTrue()
        ->and(mb_substr($brcode, -8, 4))->toBe('6304')
        ->and(mb_substr($brcode, -4))->toMatch('/^[0-9A-F]{4}$/');
});

it('recusa o CRC de um payload adulterado — o dígito verificador não é decoração', function (): void {
    $brcode = (new BuildStaticBrcode)->handle('4924.50');

    // Troca o valor sem recalcular o CRC: 4924.50 vira 1924.50, mesmo comprimento.
    $adulterado = str_replace('54074924.50', '54071924.50', $brcode);

    expect($adulterado)->not->toBe($brcode)
        ->and(EmvDecoder::crcIsValid($adulterado))->toBeFalse();
});

it('trunca nome e cidade do merchant nos limites do EMV', function (): void {
    config([
        'fake-binance-fiat.merchant_name' => 'Um Nome De Merchant Absurdamente Comprido',
        'fake-binance-fiat.merchant_city' => 'Sao Jose Dos Campos',
    ]);

    $brcode = (new BuildStaticBrcode)->handle('100.00');

    expect(EmvDecoder::value($brcode, '59'))->toBe('Um Nome De Merchant Absur')
        ->and(EmvDecoder::value($brcode, '60'))->toBe('Sao Jose Dos Ca');
});

it('dobra acento para ASCII, senão o comprimento em bytes desalinharia o resto do payload', function (): void {
    config(['fake-binance-fiat.merchant_city' => 'São Paulo']);

    $brcode = (new BuildStaticBrcode)->handle('100.00');

    expect(EmvDecoder::value($brcode, '60'))->toBe('Sao Paulo')
        ->and(EmvDecoder::crcIsValid($brcode))->toBeTrue();
});

it('volta ao default quando nome ou cidade vêm vazios de config', function (): void {
    config([
        'fake-binance-fiat.merchant_name' => '   ',
        'fake-binance-fiat.merchant_city' => '',
    ]);

    $brcode = (new BuildStaticBrcode)->handle('100.00');

    expect(EmvDecoder::value($brcode, '59'))->toBe('Fake Binance')
        ->and(EmvDecoder::value($brcode, '60'))->toBe('Sao Paulo');
});

it('calcula CRC16-CCITT-FALSE, não outra variante de CRC16', function (): void {
    // Vetor canônico da variante: "123456789" => 0x29B1.
    expect(Crc16::ccittFalse('123456789'))->toBe('29B1')
        ->and(EmvDecoder::crc16('123456789'))->toBe('29B1');
});
