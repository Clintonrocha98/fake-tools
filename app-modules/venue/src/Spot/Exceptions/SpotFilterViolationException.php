<?php

declare(strict_types=1);

namespace He4rt\Venue\Spot\Exceptions;

use RuntimeException;

/**
 * A ordem viola um filtro anunciado por `GET /api/v3/exchangeInfo`
 * (`LOT_SIZE` ou `NOTIONAL`) — mesma recusa da Binance real para uma
 * quantidade abaixo de `minQty` ou um `quoteOrderQty` abaixo de
 * `minNotional`, em vez de preencher a ordem silenciosamente.
 */
final class SpotFilterViolationException extends RuntimeException
{
    private function __construct(public readonly string $filterType, string $message)
    {
        parent::__construct($message);
    }

    public static function forFilter(string $filterType, string $message): self
    {
        return new self($filterType, $message);
    }
}
