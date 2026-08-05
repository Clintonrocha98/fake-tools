<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Actions;

use He4rt\FakeBinance\Spot\Models\SpotOrder;

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

        return $order->refresh();
    }
}
