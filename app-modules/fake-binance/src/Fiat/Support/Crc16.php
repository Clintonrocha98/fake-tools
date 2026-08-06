<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Support;

/**
 * CRC16-CCITT-FALSE — o dígito verificador que fecha o campo 63 de um BR Code
 * EMV: polinômio 0x1021, valor inicial 0xFFFF, sem reflexão de entrada ou
 * saída, XOR final 0x0000.
 *
 * Qualquer outra variante de CRC16 (ARC, XMODEM, MODBUS) produz um valor
 * plausível que todo decodificador PIX de verdade rejeita — a assinatura desta
 * classe existe para que a escolha da variante fique num lugar só.
 */
final readonly class Crc16
{
    private const int POLYNOMIAL = 0x10_21;

    private const int INITIAL = 0xFF_FF;

    /**
     * Devolve os 4 dígitos hexadecimais maiúsculos que o campo 63 do EMV espera.
     */
    public static function ccittFalse(string $payload): string
    {
        $crc = self::INITIAL;

        // Byte a byte, nunca caractere a caractere: o CRC do EMV fecha sobre os
        // bytes do payload, e um split por caractere UTF-8 engoliria as
        // continuações de um acento que tenha escapado para o texto.
        foreach (mb_str_split($payload, 1, '8bit') as $character) {
            $crc ^= ord($character) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x80_00) !== 0
                    ? (($crc << 1) ^ self::POLYNOMIAL)
                    : ($crc << 1);

                $crc &= 0xFF_FF;
            }
        }

        return mb_strtoupper(mb_str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
