<?php

declare(strict_types=1);

namespace He4rt\Venue\Ledger\DTOs;

/**
 * Um fill de execução MARKET, no mesmo vocabulário da wire da Binance (price/qty/
 * commission/commissionAsset). `qty` é a quantidade do ativo `to` recebida; `price` é
 * quanto do ativo `from` cada unidade de `to` custa — `spent = qty * price`.
 */
final readonly class LedgerFill
{
    /**
     * @param  numeric-string  $qty
     * @param  numeric-string  $price
     * @param  numeric-string  $commission
     */
    public function __construct(
        public string $qty,
        public string $price,
        public string $commission = '0',
        public ?string $commissionAsset = null,
    ) {}
}
