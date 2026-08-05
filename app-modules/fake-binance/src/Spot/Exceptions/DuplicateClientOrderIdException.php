<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Exceptions;

use RuntimeException;

/**
 * `newClientOrderId` já existe — a Binance real recusa a segunda ordem
 * (mesmo código -2010 do NEW_ORDER_REJECTED, mensagem "Duplicate order
 * sent."). O caminho seguro do fake: nunca reexecutar o swap, e o GET por
 * `origClientOrderId` sempre devolve a ordem original.
 */
final class DuplicateClientOrderIdException extends RuntimeException
{
    public static function forClientOrderId(string $clientOrderId): self
    {
        return new self(sprintf('Duplicate order sent: newClientOrderId "%s" already exists.', $clientOrderId));
    }
}
