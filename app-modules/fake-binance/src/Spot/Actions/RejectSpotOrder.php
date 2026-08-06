<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Actions;

use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Ledger\Actions\DebitLedgerAccount;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;
use He4rt\FakeBinance\Spot\Enums\SpotSymbol;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use Illuminate\Support\Facades\DB;

/**
 * Cenário do painel: recusa a ordem pela venue antes de qualquer execução —
 * mesmo estado do factory state `rejected()`, como Action de domínio para o
 * painel poder aplicá-lo a uma ordem já criada pelo happy path. O fill já
 * creditado por {@see PlaceMarketOrder} é integralmente revertido no ledger
 * antes de zerar a ordem — sem isso, `GET /api/v3/account` continuaria
 * refletindo uma troca que `GET /api/v3/order` agora nega ter acontecido.
 */
final readonly class RejectSpotOrder
{
    public function __construct(
        private ReverseSpotOrderLedgerFill $reverseLedger = new ReverseSpotOrderLedgerFill(new CreditLedgerAccount, new DebitLedgerAccount),
    ) {}

    public function handle(SpotOrder $order): SpotOrder
    {
        return DB::transaction(function () use ($order): SpotOrder {
            if (bccomp((string) $order->executed_qty, '0', 18) > 0) {
                $symbolConfig = SpotSymbol::from($order->symbol)->config();

                $this->reverseLedger->handle(
                    $order->side,
                    (string) $order->executed_qty,
                    (string) $order->cummulative_quote_qty,
                    (string) $order->commission,
                    (string) $symbolConfig['base_asset'],
                    (string) $symbolConfig['quote_asset'],
                );
            }

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
        });
    }
}
