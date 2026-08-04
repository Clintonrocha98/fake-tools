<?php

declare(strict_types=1);

namespace He4rt\Venue\Withdraw\Exceptions;

use RuntimeException;

/**
 * `config('venue-withdraw.fees')` carrega, para a rede, um valor não numérico — erro
 * de configuração, nunca de request. Falha fail-closed em vez de debitar um valor
 * incerto do ledger.
 */
final class MisconfiguredWithdrawFeeException extends RuntimeException
{
    public static function forNetwork(string $network, string $fee): self
    {
        return new self(sprintf(
            'Configured withdraw fee [%s] for network [%s] is not a valid decimal string.',
            $fee,
            $network,
        ));
    }
}
