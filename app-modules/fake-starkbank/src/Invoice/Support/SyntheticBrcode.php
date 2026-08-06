<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Support;

/**
 * O `brcode` que a invoice carrega: EMV PLAUSÍVEL, no formato do fixture do
 * consumidor, apontando para `brcode-h.starkbank.com/v2/{id}` — deliberadamente
 * não decodável.
 *
 * Quem paga uma invoice é um humano lendo o QR code; nenhum consumidor faz
 * preview dela por API. O EMV que precisa decodificar de verdade é o do
 * funding da venue, que nasce do outro lado da malha e o preview do fake
 * decodifica campo a campo.
 */
final readonly class SyntheticBrcode
{
    public static function forInvoice(string $invoiceId, int $amountCentavos, string $payerName): string
    {
        $amount = number_format($amountCentavos / 100, 2, '.', '');
        $name = mb_strtoupper(mb_substr($payerName, 0, 25));

        return sprintf(
            '00020101021226930014br.gov.bcb.pix2571brcode-h.starkbank.com/v2/%s5204000053039865%02d%s5802BR59%02d%s6009Sao Paulo62070503***6304%s',
            $invoiceId,
            mb_strlen($amount),
            $amount,
            mb_strlen($name),
            $name,
            mb_strtoupper(mb_substr(md5($invoiceId), 0, 4)),
        );
    }
}
