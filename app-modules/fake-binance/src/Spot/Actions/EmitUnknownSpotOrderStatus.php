<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Actions;

use He4rt\FakeBinance\Spot\Models\SpotOrder;
use He4rt\FakeBinance\Support\BinanceLog;

/**
 * Cenário do painel: emite um status ARBITRÁRIO (string livre, fora de
 * `OrderStatus`) — prova o fail-closed do monolito consumidor
 * (`BinanceOrderStatus::fromWire()` lança `MalformedBinanceResponse` num valor
 * não documentado). `status` na coluna real nunca muda: o cast de enum
 * explodiria num valor fora do conjunto, então o override vive numa coluna à
 * parte ({@see SpotOrderView::toWireArray()} o prioriza na serialização).
 */
final readonly class EmitUnknownSpotOrderStatus
{
    public function handle(SpotOrder $order, string $rawStatus): SpotOrder
    {
        $order->update(['raw_status_override' => $rawStatus]);

        $order = $order->refresh();

        BinanceLog::warning('fake-binance.spot: status desconhecido emitido por cenário do painel — a coluna status real não muda, e a wire responde um vocabulário fora de OrderStatus para provar o fail-closed do consumidor', [
            'order_id' => $order->order_id,
            'symbol' => $order->symbol,
            'raw_status_override' => $rawStatus,
            'client_order_id' => $order->client_order_id,
        ]);

        return $order;
    }
}
