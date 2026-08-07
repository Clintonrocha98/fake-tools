<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Actions;

use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Ledger\Actions\DebitLedgerAccount;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;
use He4rt\FakeBinance\Spot\Enums\SpotSymbol;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use He4rt\FakeBinance\Support\BinanceLog;
use Illuminate\Support\Facades\DB;

/**
 * Cenário do painel: reduz o fill à metade do que já foi executado e move
 * para EXPIRED — simula uma MARKET que não conseguiu casar o total e não é
 * reenviada. A metade é calculada a partir do fill ORIGINAL desta ordem
 * (nunca um valor fixo), para que o par parcial+EXPIRED continue coerente com
 * o `PlaceMarketOrder` que a criou. A metade removida é revertida no ledger
 * ({@see ReverseSpotOrderLedgerFill}) — sem isso, o ledger continuaria com o
 * crédito integral de uma ordem que `GET /api/v3/order` agora reporta como
 * metade preenchida.
 */
final readonly class ExpireSpotOrderPartially
{
    public function __construct(
        private ReverseSpotOrderLedgerFill $reverseLedger = new ReverseSpotOrderLedgerFill(new CreditLedgerAccount, new DebitLedgerAccount),
    ) {}

    public function handle(SpotOrder $order): SpotOrder
    {
        $executedQtyBefore = (string) $order->executed_qty;

        $order = DB::transaction(function () use ($order): SpotOrder {
            $halfExecutedQty = bcdiv((string) $order->executed_qty, '2', 18);
            $halfQuoteQty = bcdiv((string) $order->cummulative_quote_qty, '2', 18);
            $halfCommission = bcdiv((string) $order->commission, '2', 18);

            $removedExecutedQty = bcsub((string) $order->executed_qty, $halfExecutedQty, 18);
            $removedQuoteQty = bcsub((string) $order->cummulative_quote_qty, $halfQuoteQty, 18);
            $removedCommission = bcsub((string) $order->commission, $halfCommission, 18);

            if (bccomp($removedExecutedQty, '0', 18) > 0) {
                $symbolConfig = SpotSymbol::from($order->symbol)->config();

                $this->reverseLedger->handle(
                    $order->side,
                    $removedExecutedQty,
                    $removedQuoteQty,
                    $removedCommission,
                    (string) $symbolConfig['base_asset'],
                    (string) $symbolConfig['quote_asset'],
                );
            }

            $order->update([
                'status' => OrderStatus::Expired,
                'executed_qty' => $halfExecutedQty,
                'cummulative_quote_qty' => $halfQuoteQty,
                'commission' => $halfCommission,
                'raw_status_override' => null,
            ]);

            return $order->refresh();
        });

        BinanceLog::info('fake-binance.spot: ordem reduzida à metade e movida para EXPIRED por cenário do painel — simula uma MARKET que não casou o total e não foi reenviada; a metade removida do fill é revertida no ledger', [
            'order_id' => $order->order_id,
            'symbol' => $order->symbol,
            'executed_qty_antes' => $executedQtyBefore,
            'executed_qty_depois' => (string) $order->executed_qty,
            'client_order_id' => $order->client_order_id,
        ]);

        return $order;
    }
}
