<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Actions;

use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Support\BinanceLog;

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

        BinanceLog::info('fake-binance.fiat: congelamento de cenário alterado — enquanto congelada, nenhuma leitura move o status', [
            'order_no' => $order->order_no,
            'frozen' => $frozen,
            'status' => $order->status->value,
        ]);

        return $order->refresh();
    }
}
