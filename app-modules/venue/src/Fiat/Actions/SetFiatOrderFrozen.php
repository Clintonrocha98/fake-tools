<?php

declare(strict_types=1);

namespace He4rt\Venue\Fiat\Actions;

use He4rt\Venue\Fiat\Models\FiatOrder;

/**
 * Cenário do painel: congela/descongela uma ordem — enquanto `frozen`, o
 * avanço lazy ({@see GetFiatOrderDetail}) nunca calcula um novo status pela
 * idade. Descongelar retoma o avanço de onde `status` estava, sem saltar para
 * o que a idade já teria alcançado.
 */
final readonly class SetFiatOrderFrozen
{
    public function handle(FiatOrder $order, bool $frozen): FiatOrder
    {
        $order->update(['frozen' => $frozen]);

        return $order->refresh();
    }
}
