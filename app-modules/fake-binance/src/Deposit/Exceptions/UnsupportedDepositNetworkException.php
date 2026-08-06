<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Deposit\Exceptions;

use RuntimeException;

final class UnsupportedDepositNetworkException extends RuntimeException
{
    public static function forNetwork(string $network): self
    {
        return new self(sprintf(
            'Network [%s] is not mapped in fake-binance-deposit.addresses — the fake never invents an address for an unmapped chain.',
            $network,
        ));
    }
}
