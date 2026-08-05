<?php

declare(strict_types=1);

namespace He4rt\Venue\Spot\Actions;

use He4rt\Venue\Spot\DTOs\ExchangeSymbolInfo;

/**
 * As regras de negociação de um símbolo, lidas do config fixo do fake — hoje
 * só USDCBRL é servido.
 */
final readonly class GetExchangeInfo
{
    public function handle(string $symbol): ExchangeSymbolInfo
    {
        $config = config()->array('venue-spot.usdcbrl');
        $filters = $config['filters'];

        return new ExchangeSymbolInfo(
            symbol: $symbol,
            baseAsset: (string) $config['base_asset'],
            quoteAsset: (string) $config['quote_asset'],
            baseAssetPrecision: (int) $config['base_asset_precision'],
            quoteAssetPrecision: (int) $config['quote_asset_precision'],
            stepSize: (string) $filters['step_size'],
            minQty: (string) $filters['min_qty'],
            maxQty: (string) $filters['max_qty'],
            minNotional: (string) $filters['min_notional'],
        );
    }
}
