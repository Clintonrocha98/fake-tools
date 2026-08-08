<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Support;

/**
 * Compara o `apiPaymentMethod` que chega na wire com o método configurado. A
 * doc do fiat deposit escreve o método em minúsculo (`pix`) e o consumidor
 * manda `Pix`: ninguém sabe se a venue é case-sensitive, e uma recusa -16010
 * descoberta em produção é cara. Ligar
 * `fake-binance-fiat.strict_payment_method_casing` faz a comparação virar
 * literal e responde a pergunta na bancada; desligado (default) nada muda.
 */
final readonly class PaymentMethodMatcher
{
    public static function matches(string $received, string $configured): bool
    {
        if (config()->boolean('fake-binance-fiat.strict_payment_method_casing', default: false)) {
            return $received === $configured;
        }

        return mb_strtolower($received) === mb_strtolower($configured);
    }
}
