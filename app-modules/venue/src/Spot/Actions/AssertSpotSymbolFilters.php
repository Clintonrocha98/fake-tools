<?php

declare(strict_types=1);

namespace He4rt\Venue\Spot\Actions;

use He4rt\Venue\Spot\Enums\OrderSide;
use He4rt\Venue\Spot\Exceptions\SpotFilterViolationException;
use RuntimeException;

/**
 * Valida uma ordem MARKET contra os MESMOS filtros que `GET /api/v3/exchangeInfo`
 * anuncia ({@see GetExchangeInfo}), lidos do mesmo
 * config — nunca inventa um limite que o exchangeInfo não publicou. SELL é
 * recusado abaixo de `LOT_SIZE.minQty`; BUY é recusado abaixo de
 * `NOTIONAL.minNotional` (o `quoteOrderQty` de um BUY já É o notional, sem
 * precisar do preço do book).
 */
final readonly class AssertSpotSymbolFilters
{
    /**
     * @param  numeric-string|null  $quantity
     * @param  numeric-string|null  $quoteOrderQty
     */
    public function handle(OrderSide $side, ?string $quantity, ?string $quoteOrderQty): void
    {
        $filters = config()->array('venue-spot.usdcbrl.filters');
        $minQty = $this->numeric($filters, 'min_qty');
        $minNotional = $this->numeric($filters, 'min_notional');

        if ($side === OrderSide::Sell && $quantity !== null && bccomp($quantity, $minQty, 18) < 0) {
            throw SpotFilterViolationException::forFilter(
                'LOT_SIZE',
                sprintf('Filter failure: LOT_SIZE. quantity %s is below minQty %s.', $quantity, $minQty),
            );
        }

        if ($side === OrderSide::Buy && $quoteOrderQty !== null && bccomp($quoteOrderQty, $minNotional, 18) < 0) {
            throw SpotFilterViolationException::forFilter(
                'NOTIONAL',
                sprintf('Filter failure: NOTIONAL. quoteOrderQty %s is below minNotional %s.', $quoteOrderQty, $minNotional),
            );
        }
    }

    /**
     * @param  array<array-key, mixed>  $filters
     * @return numeric-string
     */
    private function numeric(array $filters, string $key): string
    {
        $value = $filters[$key] ?? null;

        throw_unless(is_numeric($value), RuntimeException::class, sprintf('venue-spot.usdcbrl.filters.%s must be numeric.', $key));

        return (string) $value;
    }
}
