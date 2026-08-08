<?php

declare(strict_types=1);

namespace He4rt\Control\Http;

use BackedEnum;
use He4rt\Control\Http\Exceptions\InvalidControlRequestException;

/**
 * String da wire → case do enum do fake, ou 422 com a lista do que vale.
 *
 * `Enum::from()` cru lançaria `ValueError`, que vira 500 — e um status
 * inexistente é pedido malformado, não defeito do fake.
 */
final readonly class WireEnum
{
    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T
     */
    public static function resolve(string $enum, string $valor): BackedEnum
    {
        // Comparação como string cobre os dois backings sem ramificar: o status
        // do saque é enum de int na wire da Binance, o da invoice é de string,
        // e `tryFrom()` com o tipo errado lançaria TypeError.
        foreach ($enum::cases() as $caso) {
            if ((string) $caso->value === $valor) {
                return $caso;
            }
        }

        throw InvalidControlRequestException::unknownStatus($valor, $enum::cases());
    }
}
