<?php

declare(strict_types=1);

namespace He4rt\Venue\Fiat\Actions;

use He4rt\Venue\Fiat\Enums\FiatOrderStatus;
use He4rt\Venue\Fiat\Models\FiatOrder;

/**
 * Cenário do painel: força `forced_status` — a máscara de leitura que vence o
 * avanço lazy sem nunca gravar em `status` (ver {@see FiatOrder::effectiveStatus()}).
 * `forced_wire_status` é sempre limpo junto: os dois overrides são mutuamente
 * exclusivos, e este é o vocabulário CONHECIDO (dentro de `FiatOrderStatus`),
 * ao contrário de {@see EmitUnknownFiatWireStatus}.
 */
final readonly class ForceFiatOrderStatus
{
    public function handle(FiatOrder $order, FiatOrderStatus $status): FiatOrder
    {
        $order->update([
            'forced_status' => $status,
            'forced_wire_status' => null,
        ]);

        return $order->refresh();
    }
}
