<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Actions;

use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Ledger\Actions\DebitLedgerAccount;
use He4rt\FakeBinance\Spot\Enums\OrderSide;
use Illuminate\Support\Facades\DB;

/**
 * O inverso exato de {@see \He4rt\FakeBinance\Ledger\Actions\SwapLedgerAssets} para uma
 * fração já liquidada de uma ordem Spot: credita de volta o que foi gasto e debita o
 * que foi recebido líquido da comissão (a comissão incide sobre o ativo recebido,
 * nunca sobre o gasto — mesma convenção de {@see PlaceMarketOrder}).
 * Usada pelos cenários do painel que apagam ou reduzem um fill já creditado
 * (`RejectSpotOrder`, `ExpireSpotOrderPartially`) — sem isso, `GET /api/v3/order` e
 * `GET /api/v3/account` contam histórias diferentes após o clique.
 */
final readonly class ReverseSpotOrderLedgerFill
{
    public function __construct(
        private CreditLedgerAccount $credit,
        private DebitLedgerAccount $debit,
    ) {}

    /**
     * @param  numeric-string  $executedQtyDelta  Quantidade base do fill a reverter
     * @param  numeric-string  $quoteQtyDelta  Quantidade quote do fill a reverter
     * @param  numeric-string  $commissionDelta  Comissão (no ativo recebido) do fill a reverter
     */
    public function handle(
        OrderSide $side,
        string $executedQtyDelta,
        string $quoteQtyDelta,
        string $commissionDelta,
        string $baseAsset,
        string $quoteAsset,
    ): void {
        DB::transaction(function () use ($side, $executedQtyDelta, $quoteQtyDelta, $commissionDelta, $baseAsset, $quoteAsset): void {
            // BUY original: from=quote (spent=quoteQtyDelta), to=base (received líquido =
            // executedQtyDelta - commissionDelta). SELL é o inverso.
            [$spentAsset, $spentAmount, $receivedAsset, $receivedNetAmount] = $side === OrderSide::Buy
                ? [$quoteAsset, $quoteQtyDelta, $baseAsset, bcsub($executedQtyDelta, $commissionDelta, 18)]
                : [$baseAsset, $executedQtyDelta, $quoteAsset, bcsub($quoteQtyDelta, $commissionDelta, 18)];

            $this->credit->handle($spentAsset, $spentAmount);
            $this->debit->handle($receivedAsset, $receivedNetAmount);
        });
    }
}
