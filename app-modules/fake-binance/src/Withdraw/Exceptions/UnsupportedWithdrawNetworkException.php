<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\Exceptions;

use RuntimeException;

/**
 * Rede fora de `fake-binance-withdraw.fees` — fail-closed em vez de cobrar `default_fee`
 * e transmitir para uma chain não mapeada: um withdraw na chain errada é
 * irrecuperável, então nunca inventamos uma taxa para uma rede desconhecida.
 */
final class UnsupportedWithdrawNetworkException extends RuntimeException
{
    public static function forNetwork(string $network): self
    {
        return new self(sprintf(
            'Network [%s] has no fee configured in fake-binance-withdraw.fees.',
            $network,
        ));
    }
}
