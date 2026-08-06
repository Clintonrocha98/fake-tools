<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Actions;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Ledger\Actions\SwapLedgerAssets;
use He4rt\FakeBinance\Ledger\DTOs\LedgerFill;
use He4rt\FakeBinance\Ledger\Enums\Side as LedgerSide;
use He4rt\FakeBinance\Scenarios\Exceptions\ScenarioRefusedRequestException;
use He4rt\FakeBinance\Spot\DTOs\PlaceMarketOrderData;
use He4rt\FakeBinance\Spot\DTOs\SpotExecutionPlan;
use He4rt\FakeBinance\Spot\Enums\OrderSide;
use He4rt\FakeBinance\Spot\Exceptions\DuplicateClientOrderIdException;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Executa UMA ordem MARKET ao preço do book (sem re-order loop — a mesma
 * disciplina do `BinanceMarketExecution` do monolito consumidor: uma MARKET
 * não fica pendente, preenche de uma vez). BUY preenche no ask, SELL no bid;
 * a denominação é ortogonal ao lado — `quantity` (base) executa como veio,
 * `quoteOrderQty` (quote) vira base por `quoteOrderQty / preço`, floored à
 * precisão da base. A comissão incide sobre o ativo recebido — nunca sobre o
 * gasto — e o `SwapLedgerAssets` já aplica essa dedução ao creditar o ledger.
 *
 * O desfecho vem de um {@see SpotExecutionPlan}: no happy path é o plano
 * neutro (fração `1`, FILLED), e um cenário armado o substitui por parcial,
 * recusa ou vocabulário desconhecido. O ledger recebe exatamente a fração
 * executada — nunca o fill cheio seguido de estorno.
 */
final readonly class PlaceMarketOrder
{
    /**
     * Escala usada para `cummulativeQuoteQty` — a MESMA que
     * {@see SwapLedgerAssets} usa ao recalcular
     * `qty * price` para o movimento do ledger. Se essa conta aqui truncasse
     * numa precisão diferente (ex.: `quote_asset_precision` = 8), a wire
     * reportaria um `cummulativeQuoteQty` diferente do que é debitado —
     * mesmos `qty`/`price`, escalas diferentes, dois números diferentes.
     */
    private const int LEDGER_SCALE = 18;

    public function __construct(
        private GetBookTicker $bookTicker,
        private NextSpotOrderId $nextOrderId,
        private SwapLedgerAssets $swap,
        private AssertSpotSymbolFilters $assertFilters,
        private PlanNextSpotExecution $planNextExecution,
    ) {}

    public function handle(PlaceMarketOrderData $data): SpotOrder
    {
        if (SpotOrder::query()->where('client_order_id', $data->newClientOrderId)->exists()) {
            throw DuplicateClientOrderIdException::forClientOrderId($data->newClientOrderId);
        }

        $this->assertFilters->handle($data->quantity, $data->quoteOrderQty);

        $plan = $this->planNextExecution->handle();

        if ($plan->refusal instanceof BinanceErrorCode) {
            throw ScenarioRefusedRequestException::withCode($plan->refusal);
        }

        $symbolConfig = config()->array('fake-binance-spot.usdcbrl');
        $baseAsset = (string) $symbolConfig['base_asset'];
        $quoteAsset = (string) $symbolConfig['quote_asset'];
        $basePrecision = (int) $symbolConfig['base_asset_precision'];
        $commissionRateValue = config()->string('fake-binance-spot.commission_rate');

        throw_unless(is_numeric($commissionRateValue), RuntimeException::class, 'fake-binance-spot.commission_rate must be numeric.');

        $commissionRate = $commissionRateValue;

        $book = $this->bookTicker->handle($data->symbol);

        $price = $data->side === OrderSide::Buy ? $book->askPrice : $book->bidPrice;
        $receivedAsset = $data->side === OrderSide::Buy ? $baseAsset : $quoteAsset;

        $executedQty = $plan->applyFraction($this->executedBaseQty($data, $price, $basePrecision), $basePrecision);
        $cummulativeQuoteQty = $this->quoteTotal($executedQty, $price);

        $receivedGross = $this->receivedGross($data->side, $executedQty, $cummulativeQuoteQty);
        $commission = bcmul($receivedGross, $commissionRate, 18);

        $order = DB::transaction(function () use (
            $data, $executedQty, $cummulativeQuoteQty, $price, $commission, $receivedAsset, $baseAsset, $quoteAsset, $plan,
        ): SpotOrder {
            if (!$plan->fillsNothing()) {
                $this->swap->handle(
                    from: $data->side === OrderSide::Buy ? $quoteAsset : $baseAsset,
                    to: $data->side === OrderSide::Buy ? $baseAsset : $quoteAsset,
                    fills: [new LedgerFill(qty: $executedQty, price: $price, commission: $commission, commissionAsset: $receivedAsset)],
                    side: $data->side === OrderSide::Buy ? LedgerSide::Buy : LedgerSide::Sell,
                );
            }

            return SpotOrder::query()->create([
                'order_id' => $this->nextOrderId->handle(),
                'client_order_id' => $data->newClientOrderId,
                'symbol' => $data->symbol,
                'side' => $data->side,
                'type' => 'MARKET',
                'status' => $plan->finalStatus,
                'quantity' => $data->quantity,
                'quote_order_qty' => $data->quoteOrderQty,
                'executed_qty' => $executedQty,
                'cummulative_quote_qty' => $cummulativeQuoteQty,
                'fill_price' => $plan->fillsNothing() ? null : $price,
                'commission' => $plan->fillsNothing() ? '0' : $commission,
                'commission_asset' => $plan->fillsNothing() ? null : $receivedAsset,
                'raw_status_override' => $plan->rawStatusOverride,
            ]);
        });

        Log::info('fake-binance.spot: ordem MARKET registrada', [
            'symbol' => $order->symbol,
            'side' => $order->side->value,
            'denomination' => $data->quantity !== null ? 'base (quantity)' : 'quote (quoteOrderQty)',
            'requested' => $data->quantity ?? $data->quoteOrderQty,
            'executed_qty' => $executedQty,
            'cummulative_quote_qty' => $cummulativeQuoteQty,
            'fill_price' => $price,
            'status' => $order->raw_status_override ?? $order->status->value,
            'client_order_id' => $order->client_order_id,
        ]);

        return $order;
    }

    /**
     * O total quote de um fill é SEMPRE `qty * price` sobre o `executedQty`
     * final — o mesmo produto, na mesma escala, que {@see SwapLedgerAssets}
     * refaz para mover o ledger. Derivar aqui, depois da fração, é o que
     * mantém `cummulativeQuoteQty` igual ao que foi debitado e
     * `fills[0].qty * fills[0].price == cummulativeQuoteQty` na wire: fracionar
     * o quote em paralelo ao qty separa os dois em qualquer fração que não
     * feche na precisão da base.
     *
     * @param  numeric-string  $executedQty
     * @param  numeric-string  $price
     * @return numeric-string
     */
    private function quoteTotal(string $executedQty, string $price): string
    {
        return bcmul($executedQty, $price, self::LEDGER_SCALE);
    }

    /**
     * A quantidade base executada, qualquer que seja a denominação do pedido:
     * `quantity` já É a base; `quoteOrderQty` vira base por `quoteOrderQty /
     * preço` — floored à precisão da base, nunca arredondado para cima, para
     * nunca entregar (BUY) nem vender (SELL) mais do que o preço do book
     * realmente cobre.
     *
     * @param  numeric-string  $price
     * @return numeric-string
     */
    private function executedBaseQty(PlaceMarketOrderData $data, string $price, int $basePrecision): string
    {
        if ($data->quantity !== null) {
            return $data->quantity;
        }

        throw_if($data->quoteOrderQty === null, RuntimeException::class, 'Exactly one of quantity or quoteOrderQty must be provided for a MARKET order.');

        return bcdiv($data->quoteOrderQty, $price, $basePrecision);
    }

    /**
     * @param  numeric-string  $executedQty
     * @param  numeric-string  $cummulativeQuoteQty
     * @return numeric-string
     */
    private function receivedGross(OrderSide $side, string $executedQty, string $cummulativeQuoteQty): string
    {
        return $side === OrderSide::Buy ? $executedQty : $cummulativeQuoteQty;
    }
}
