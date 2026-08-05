<?php

declare(strict_types=1);

namespace He4rt\Venue\Fiat\Actions;

use He4rt\Venue\Fiat\Models\FiatOrder;

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

        return $order->refresh();
    }
}
