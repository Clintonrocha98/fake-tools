<?php

declare(strict_types=1);

namespace He4rt\Venue\Spot\Actions;

use He4rt\Venue\Spot\Enums\OrderStatus;
use He4rt\Venue\Spot\Models\SpotOrder;

/**
 * Cenário do painel: recusa a ordem pela venue antes de qualquer execução —
 * mesmo estado do factory state `rejected()`, como Action de domínio para o
 * painel poder aplicá-lo a uma ordem já criada pelo happy path.
 */
final readonly class RejectSpotOrder
{
    public function __invoke(SpotOrder $order): SpotOrder
    {
        $order->update([
            'status' => OrderStatus::Rejected,
            'executed_qty' => '0',
            'cummulative_quote_qty' => '0',
            'fill_price' => null,
            'commission' => '0',
            'commission_asset' => null,
            'raw_status_override' => null,
        ]);

        return $order->refresh();
    }
}
