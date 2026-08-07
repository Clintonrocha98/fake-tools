<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Actions;

use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Support\BinanceLog;

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
        $previousForced = $order->forced_status;

        $order->update([
            'forced_status' => $status,
            'forced_wire_status' => null,
        ]);

        $order->refresh();

        BinanceLog::info('fake-binance.fiat: status forçado por cenário — a máscara de leitura vence o avanço lazy sem gravar em `status`, para exercitar um ramo que o consumidor trata mas raramente vê', [
            'order_no' => $order->order_no,
            'from' => $previousForced?->value,
            'to' => $status->value,
        ]);

        return $order;
    }
}
