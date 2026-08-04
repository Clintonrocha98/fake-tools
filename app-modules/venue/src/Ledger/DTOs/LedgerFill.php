<?php

declare(strict_types=1);

namespace He4rt\Venue\Ledger\DTOs;

/**
 * Um fill de execução MARKET, no mesmo vocabulário da wire da Binance (price/qty/
 * commission/commissionAsset): `qty` é SEMPRE a quantidade do ativo BASE do símbolo,
 * qualquer que seja o side — nunca do ativo `to`. `price` é quanto do ativo QUOTE
 * cada unidade da base custa, então `qty * price` é sempre o total em QUOTE do fill.
 * Qual de `from`/`to` é base ou quote depende do {@see \He4rt\Venue\Ledger\Enums\Side}
 * passado a {@see \He4rt\Venue\Ledger\Actions\SwapLedgerAssets} — este DTO não
 * reinterpreta o par, só carrega os campos que a wire manda.
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
