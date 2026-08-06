<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Actions;

use He4rt\FakeBinance\Spot\DTOs\BookTicker;
use He4rt\FakeBinance\Spot\Enums\SpotSymbol;
use RuntimeException;

/**
 * Deriva bid/ask do mid price fixo por config do símbolo: bid = price -
 * spread/2, ask = price + spread/2. Os preços ficam com a escala cheia aqui
 * (a mesma execução MARKET reusa o valor para multiplicar quantidade); só
 * {@see BookTicker::toWireArray()} apara os zeros à direita para a resposta.
 */
final readonly class GetBookTicker
{
    public function handle(SpotSymbol $symbol): BookTicker
    {
        $config = $symbol->config();

        $price = $this->numeric($symbol, $config, 'price');
        $spread = $this->numeric($symbol, $config, 'spread');
        $halfSpread = bcdiv($spread, '2', 18);

        return new BookTicker(
            symbol: $symbol->value,
            bidPrice: bcsub($price, $halfSpread, 8),
            bidQty: $this->numeric($symbol, $config, 'bid_qty'),
            askPrice: bcadd($price, $halfSpread, 8),
            askQty: $this->numeric($symbol, $config, 'ask_qty'),
        );
    }

    /**
     * @param  array<array-key, mixed>  $config
     * @return numeric-string
     */
    private function numeric(SpotSymbol $symbol, array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if (!is_numeric($value)) {
            throw new RuntimeException(sprintf('fake-binance-spot.symbols.%s.%s must be numeric.', $symbol->value, $key));
        }

        return (string) $value;
    }
}
