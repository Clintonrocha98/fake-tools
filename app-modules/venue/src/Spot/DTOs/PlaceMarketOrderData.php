<?php

declare(strict_types=1);

namespace He4rt\Venue\Spot\DTOs;

use He4rt\Venue\Spot\Enums\OrderSide;

/**
 * O que o monolito consumidor envia em POST /api/v3/order (`PlaceSpotOrderRequest`):
 * `type=MARKET` é sempre implícito, e exatamente um de `quoteOrderQty`/`quantity`
 * é informado — `quoteOrderQty` no BUY (gasta o quote), `quantity` no SELL
 * (vende uma quantidade fixa da base, já floored ao lot step pelo chamador).
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
