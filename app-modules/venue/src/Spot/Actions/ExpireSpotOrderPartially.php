<?php

declare(strict_types=1);

namespace He4rt\Venue\Spot\Actions;

use He4rt\Venue\Spot\Enums\OrderStatus;
use He4rt\Venue\Spot\Models\SpotOrder;

/**
 * Cenário do painel: reduz o fill à metade do que já foi executado e move
 * para EXPIRED — simula uma MARKET que não conseguiu casar o total e não é
 * reenviada. A metade é calculada a partir do fill ORIGINAL desta ordem
 * (nunca um valor fixo), para que o par parcial+EXPIRED continue coerente com
 * o `PlaceMarketOrder` que a criou.
 */
final readonly class ExpireSpotOrderPartially
{
    public function __invoke(SpotOrder $order): SpotOrder
    {
        $order->update([
            'status' => OrderStatus::Expired,
            'executed_qty' => bcdiv((string) $order->executed_qty, '2', 18),
            'cummulative_quote_qty' => bcdiv((string) $order->cummulative_quote_qty, '2', 18),
            'commission' => bcdiv((string) $order->commission, '2', 18),
            'raw_status_override' => null,
        ]);

        return $order->refresh();
    }
}
