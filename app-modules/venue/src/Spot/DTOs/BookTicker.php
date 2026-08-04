<?php

declare(strict_types=1);

namespace He4rt\Venue\Spot\DTOs;

use He4rt\Venue\Ledger\Support\LedgerAmount;

/**
 * O melhor bid/ask de um símbolo, no mesmo vocabulário decimal-string da wire
 * da Binance — nunca floats. O monolito consumidor lê os quatro campos de
 * preço/quantidade assim.
 */
final readonly class BookTicker
{
    /**
     * @param  numeric-string  $bidPrice
     * @param  numeric-string  $bidQty
     * @param  numeric-string  $askPrice
     * @param  numeric-string  $askQty
     */
    public function __construct(
        public string $symbol,
        public string $bidPrice,
        public string $bidQty,
        public string $askPrice,
        public string $askQty,
    ) {}

    /**
     * @return array{symbol: string, bidPrice: string, bidQty: string, askPrice: string, askQty: string}
     */
    public function toWireArray(): array
    {
        return [
            'symbol' => $this->symbol,
            'bidPrice' => LedgerAmount::wire($this->bidPrice),
            'bidQty' => LedgerAmount::wire($this->bidQty),
            'askPrice' => LedgerAmount::wire($this->askPrice),
            'askQty' => LedgerAmount::wire($this->askQty),
        ];
    }
}
