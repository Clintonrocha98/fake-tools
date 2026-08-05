<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Ledger\Exceptions;

use RuntimeException;

final class InsufficientLedgerBalanceException extends RuntimeException
{
    /**
     * @param  numeric-string  $requested
     * @param  numeric-string  $available
     */
    public static function forAsset(string $asset, string $requested, string $available): self
    {
        return new self(sprintf(
            'Insufficient %s balance: available %s, requested %s.',
            $asset,
            $available,
            $requested,
        ));
    }
}
