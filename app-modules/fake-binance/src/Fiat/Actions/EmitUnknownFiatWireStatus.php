<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Actions;

use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Support\BinanceLog;

/**
 * Cenário do painel: emite um vocabulário de wire ARBITRÁRIO (string livre,
 * fora de `FiatOrderStatus`) — prova o fail-closed do monolito consumidor
 * (fica Pending, o default de `BinanceFiatOrderStatus`). Enquanto setado, a
 * ordem nunca avança e nunca credita ({@see GetFiatOrderDetail}).
 * `forced_status` é sempre limpo junto: os dois overrides são mutuamente exclusivos.
 */
final readonly class EmitUnknownFiatWireStatus
{
    public function handle(FiatOrder $order, string $wireStatus): FiatOrder
    {
        $order->update([
            'forced_wire_status' => $wireStatus,
            'forced_status' => null,
        ]);

        BinanceLog::warning('fake-binance.fiat: vocabulário de wire arbitrário emitido por cenário — fora de FiatOrderStatus, para provar o fail-closed do consumidor (cai em Pending)', [
            'order_no' => $order->order_no,
            'forced_wire_status' => $wireStatus,
        ]);

        return $order->refresh();
    }
}
