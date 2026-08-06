<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\DTOs;

use He4rt\FakeBinance\Spot\Enums\OrderSide;

/**
 * O que o monolito consumidor envia em POST /api/v3/order (`PlaceSpotOrderRequest`):
 * `type=MARKET` é sempre implícito, e exatamente um de `quoteOrderQty`/`quantity`
 * é informado — a denominação é ortogonal ao lado (`BinanceMarket` do consumidor
 * escolhe pelo par lado × basis): `quantity` denomina a base, `quoteOrderQty` o
 * quote, tanto no BUY quanto no SELL.
 */
final readonly class PlaceMarketOrderData
{
    /**
     * @param  numeric-string|null  $quoteOrderQty
     * @param  numeric-string|null  $quantity
     */
    public function __construct(
        public string $symbol,
        public OrderSide $side,
        public string $newClientOrderId,
        public ?string $quoteOrderQty = null,
        public ?string $quantity = null,
    ) {}
}
