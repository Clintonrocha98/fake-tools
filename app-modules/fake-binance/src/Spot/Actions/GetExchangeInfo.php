<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Actions;

use He4rt\FakeBinance\Spot\DTOs\ExchangeSymbolInfo;
use He4rt\FakeBinance\Spot\Enums\SpotSymbol;

/**
 * As regras de negociação de um símbolo, lidas do config fixo do fake —
 * resolvidas pelo caso do {@see SpotSymbol}, nunca por uma chave literal.
 */
final readonly class GetExchangeInfo
{
    public function handle(SpotSymbol $symbol): ExchangeSymbolInfo
    {
        $config = $symbol->config();
        $filters = $config['filters'];

        return new ExchangeSymbolInfo(
            symbol: $symbol->value,
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
