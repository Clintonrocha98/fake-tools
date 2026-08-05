<?php

declare(strict_types=1);

namespace He4rt\Venue\Spot\DTOs;

/**
 * As regras de negociação de um símbolo — o que o `SymbolFilterProvider` do
 * monolito consumidor usa para arredondar a quantidade ao passo do lote antes
 * de um SELL. `toWireArray()` monta o shape aninhado (`symbols[].filters[]`)
 * só na fronteira de serialização; os campos tipados vivem soltos aqui.
 */
final readonly class ExchangeSymbolInfo
{
    public function __construct(
        public string $symbol,
        public string $baseAsset,
        public string $quoteAsset,
        public int $baseAssetPrecision,
        public int $quoteAssetPrecision,
        public string $stepSize,
        public string $minQty,
        public string $maxQty,
        public string $minNotional,
    ) {}

    /**
     * @return array{
     *     symbol: string,
     *     baseAsset: string,
     *     quoteAsset: string,
     *     baseAssetPrecision: int,
     *     quoteAssetPrecision: int,
     *     filters: list<array<string, mixed>>,
     * }
     */
    public function toWireArray(): array
    {
        return [
            'symbol' => $this->symbol,
            'baseAsset' => $this->baseAsset,
            'quoteAsset' => $this->quoteAsset,
            'baseAssetPrecision' => $this->baseAssetPrecision,
            'quoteAssetPrecision' => $this->quoteAssetPrecision,
            'filters' => [
                [
                    'filterType' => 'LOT_SIZE',
                    'minQty' => $this->minQty,
                    'maxQty' => $this->maxQty,
                    'stepSize' => $this->stepSize,
                ],
                [
                    'filterType' => 'NOTIONAL',
                    'minNotional' => $this->minNotional,
                    'applyToMarket' => true,
                ],
            ],
        ];
    }
}
