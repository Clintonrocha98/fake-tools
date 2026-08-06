<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Actions;

use He4rt\FakeBinance\Ledger\Support\LedgerAmount;
use He4rt\FakeBinance\Spot\Enums\SpotSymbol;
use RuntimeException;

/**
 * GET /api/v3/ticker/price: o MID do config do símbolo, sem spread — o mesmo
 * mid do qual {@see GetBookTicker} deriva bid/ask. O shape `{symbol, price}`
 * é o que o `TickerPriceResponse` do monolito consumidor lê.
 */
final readonly class GetTickerPrice
{
    /**
     * @return array{symbol: string, price: string}
     */
    public function handle(SpotSymbol $symbol): array
    {
        $price = $symbol->config()['price'] ?? null;

        if (!is_numeric($price)) {
            throw new RuntimeException(sprintf('fake-binance-spot.symbols.%s.price must be numeric.', $symbol->value));
        }

        return [
            'symbol' => $symbol->value,
            'price' => LedgerAmount::wire((string) $price),
        ];
    }
}
