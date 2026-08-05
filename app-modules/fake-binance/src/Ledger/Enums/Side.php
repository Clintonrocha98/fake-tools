<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Ledger\Enums;

/**
 * O lado de uma ordem MARKET, no mesmo vocabulário da wire da Binance: em BUY o ativo
 * `from` é a quote (gasta) e o `to` é a base (recebida); em SELL é o inverso — `from` é
 * a base (gasta) e `to` é a quote (recebida). {@see \He4rt\FakeBinance\Ledger\Actions\SwapLedgerAssets}
 * usa este lado para mapear os totais de `qty`/`price` em spent/received corretamente.
 */
enum Side
{
    case Buy;
    case Sell;
}
