<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Actions;

use He4rt\FakeBinance\Fiat\Support\Crc16;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Monta o BR Code EMV estático que vira o `pixcode` da FiatOrder — TLV de
 * verdade, com CRC16 de verdade, decodificável por qualquer leitor PIX.
 *
 * O valor viaja no campo 54 e sai sempre do `amount` da ordem, nunca de
 * config: é ele que o consumidor confere no preview contra
 * `Conversion.requested_centavos`. Um BR Code de valor fixo faria esse guard
 * passar sempre ou falhar sempre — nunca exercitá-lo.
 *
 * A chave PIX do campo 26 é a metade deste lado do contrato cross-fake
 * (`fake-binance-fiat.pix_key`): é ela que o fake-starkbank resolve no seu
 * registro DICT para devolver o taxId do recebedor.
 */
final readonly class BuildStaticBrcode
{
    /**
     * Globally Unique Identifier do arranjo PIX dentro do Merchant Account
     * Information (campo 26, sub 00) — fixado pelo Banco Central.
     */
    private const string PIX_GUI = 'br.gov.bcb.pix';

    private const int MERCHANT_NAME_LIMIT = 25;

    private const int MERCHANT_CITY_LIMIT = 15;

    /**
     * `$amount` chega como a decimal-string que a FiatOrder guarda (escala 18)
     * ou como o `amount` cru da wire — os dois viram os mesmos centavos no
     * campo 54.
     */
    public function handle(string $amount): string
    {
        $payload = $this->tlv('00', '01')
            .$this->tlv('26', $this->merchantAccountInformation())
            .$this->tlv('52', '0000')
            .$this->tlv('53', '986')
            .$this->tlv('54', $this->wireAmount($amount))
            .$this->tlv('58', 'BR')
            .$this->tlv('59', $this->boundedAscii('fake-binance-fiat.merchant_name', 'Fake Binance', self::MERCHANT_NAME_LIMIT))
            .$this->tlv('60', $this->boundedAscii('fake-binance-fiat.merchant_city', 'Sao Paulo', self::MERCHANT_CITY_LIMIT))
            .$this->tlv('62', $this->tlv('05', '***'));

        // O CRC fecha sobre o payload já com o cabeçalho `6304` anexado: os
        // quatro dígitos calculados entram depois, fora da conta.
        $checked = $payload.'6304';

        return $checked.Crc16::ccittFalse($checked);
    }

    private function merchantAccountInformation(): string
    {
        $pixKey = config()->string('fake-binance-fiat.pix_key', 'funding@fake-binance.dev');

        return $this->tlv('00', self::PIX_GUI).$this->tlv('01', $pixKey);
    }

    /**
     * O campo 54 é decimal com ponto e duas casas, montado por aritmética de
     * string — um float perderia centavos exatamente no valor que o consumidor
     * confere.
     */
    private function wireAmount(string $amount): string
    {
        if (!is_numeric($amount)) {
            Log::warning('fake-binance.fiat: amount ilegível no BR Code — campo 54 emitido como 0.00 para o guard de valor do consumidor recusar alto, em vez de o encoder derrubar o depósito', [
                'amount' => $amount,
            ]);

            return '0.00';
        }

        return bcadd($amount, '0', 2);
    }

    /**
     * Nome e cidade do merchant viajam em ASCII: o comprimento do TLV é contado
     * em bytes, e um acento faria o byte e o caractere divergirem no meio do
     * payload — todo decodificador que caminha por offset se perderia dali para
     * a frente.
     */
    private function boundedAscii(string $configKey, string $fallback, int $limit): string
    {
        $value = mb_trim(config()->string($configKey, $fallback));

        if ($value === '') {
            $value = $fallback;
        }

        return mb_substr(Str::ascii($value), 0, $limit);
    }

    private function tlv(string $tag, string $value): string
    {
        return $tag.mb_str_pad((string) mb_strlen($value, '8bit'), 2, '0', STR_PAD_LEFT).$value;
    }
}
