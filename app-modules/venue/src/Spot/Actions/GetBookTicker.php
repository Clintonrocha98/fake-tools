<?php

declare(strict_types=1);

namespace He4rt\Venue\Spot\Actions;

use He4rt\Venue\Spot\DTOs\BookTicker;
use RuntimeException;

/**
 * Deriva bid/ask do mid price fixo por config: bid = price - spread/2,
 * ask = price + spread/2 — o único par servido hoje é USDCBRL. Os preços
 * ficam com a escala cheia aqui (a mesma execução MARKET reusa o valor para
 * multiplicar quantidade); só {@see BookTicker::toWireArray()} apara os
 * zeros à direita para a resposta.
 */
final readonly class GetBookTicker
{
    public function __invoke(string $symbol): BookTicker
    {
        $config = config()->array('venue-spot.usdcbrl');

        $price = $this->numeric($config, 'price');
        $spread = $this->numeric($config, 'spread');
        $halfSpread = bcdiv($spread, '2', 18);

        return new BookTicker(
            symbol: $symbol,
            bidPrice: bcsub($price, $halfSpread, 8),
            bidQty: $this->numeric($config, 'bid_qty'),
            askPrice: bcadd($price, $halfSpread, 8),
            askQty: $this->numeric($config, 'ask_qty'),
        );
    }

    /**
     * @param  array<array-key, mixed>  $config
     * @return numeric-string
     */
    private function numeric(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if (!is_numeric($value)) {
            throw new RuntimeException(sprintf('venue-spot.usdcbrl.%s must be numeric.', $key));
        }

        return (string) $value;
    }
}
