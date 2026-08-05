<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Actions;

use He4rt\FakeBinance\Ledger\Actions\SwapLedgerAssets;
use He4rt\FakeBinance\Ledger\DTOs\LedgerFill;
use He4rt\FakeBinance\Ledger\Enums\Side as LedgerSide;
use He4rt\FakeBinance\Spot\DTOs\PlaceMarketOrderData;
use He4rt\FakeBinance\Spot\Enums\OrderSide;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;
use He4rt\FakeBinance\Spot\Exceptions\DuplicateClientOrderIdException;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Executa UMA ordem MARKET ao preço do book (sem re-order loop — a mesma
 * disciplina do `BinanceMarketExecution` do monolito consumidor: uma MARKET
 * não fica pendente, preenche de uma vez). BUY preenche no ask e gasta
 * `quoteOrderQty`; SELL preenche no bid e vende `quantity`. A comissão incide
 * sobre o ativo recebido — nunca sobre o gasto — e o `SwapLedgerAssets` já
 * aplica essa dedução ao creditar o ledger.
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
    ) {}

    public function handle(PlaceMarketOrderData $data): SpotOrder
    {
        if (SpotOrder::query()->where('client_order_id', $data->newClientOrderId)->exists()) {
            throw DuplicateClientOrderIdException::forClientOrderId($data->newClientOrderId);
        }

        $this->assertFilters->handle($data->side, $data->quantity, $data->quoteOrderQty);

        $symbolConfig = config()->array('fake-binance-spot.usdcbrl');
        $baseAsset = (string) $symbolConfig['base_asset'];
        $quoteAsset = (string) $symbolConfig['quote_asset'];
        $basePrecision = (int) $symbolConfig['base_asset_precision'];
        $commissionRateValue = config()->string('fake-binance-spot.commission_rate');

        throw_unless(is_numeric($commissionRateValue), RuntimeException::class, 'fake-binance-spot.commission_rate must be numeric.');

        $commissionRate = $commissionRateValue;

        $book = $this->bookTicker->handle($data->symbol);

        [$executedQty, $cummulativeQuoteQty, $price, $receivedAsset] = $data->side === OrderSide::Buy
            ? $this->quoteSpend($data, $book->askPrice, $basePrecision, $baseAsset)
            : $this->baseSell($data, $book->bidPrice, $quoteAsset);

        $receivedGross = $this->receivedGross($data->side, $executedQty, $cummulativeQuoteQty);
        $commission = bcmul($receivedGross, $commissionRate, 18);

        return DB::transaction(function () use (
            $data, $executedQty, $cummulativeQuoteQty, $price, $commission, $receivedAsset, $baseAsset, $quoteAsset,
        ): SpotOrder {
            $this->swap->handle(
                from: $data->side === OrderSide::Buy ? $quoteAsset : $baseAsset,
                to: $data->side === OrderSide::Buy ? $baseAsset : $quoteAsset,
                fills: [new LedgerFill(qty: $executedQty, price: $price, commission: $commission, commissionAsset: $receivedAsset)],
                side: $data->side === OrderSide::Buy ? LedgerSide::Buy : LedgerSide::Sell,
            );

            return SpotOrder::query()->create([
                'order_id' => $this->nextOrderId->handle(),
                'client_order_id' => $data->newClientOrderId,
                'symbol' => $data->symbol,
                'side' => $data->side,
                'type' => 'MARKET',
                'status' => OrderStatus::Filled,
                'quantity' => $data->quantity,
                'quote_order_qty' => $data->quoteOrderQty,
                'executed_qty' => $executedQty,
                'cummulative_quote_qty' => $cummulativeQuoteQty,
                'fill_price' => $price,
                'commission' => $commission,
                'commission_asset' => $receivedAsset,
            ]);
        });
    }

    /**
     * BUY: gasta `quoteOrderQty` no ask, recebe base. `executedQty` é
     * floored à precisão da base — nunca arredondado para cima, para nunca
     * entregar mais do que o preço do book realmente compra.
     *
     * @param  numeric-string  $askPrice
     * @return array{0: numeric-string, 1: numeric-string, 2: numeric-string, 3: string}
     */
    private function quoteSpend(PlaceMarketOrderData $data, string $askPrice, int $basePrecision, string $baseAsset): array
    {
        throw_if($data->quoteOrderQty === null, RuntimeException::class, 'quoteOrderQty is required for a BUY MARKET order.');

        $executedQty = bcdiv($data->quoteOrderQty, $askPrice, $basePrecision);
        $cummulativeQuoteQty = bcmul($executedQty, $askPrice, self::LEDGER_SCALE);

        return [$executedQty, $cummulativeQuoteQty, $askPrice, $baseAsset];
    }

    /**
     * SELL: vende `quantity` (já floored ao lot step pelo chamador) no bid,
     * recebe quote.
     *
     * @param  numeric-string  $bidPrice
     * @return array{0: numeric-string, 1: numeric-string, 2: numeric-string, 3: string}
     */
    private function baseSell(PlaceMarketOrderData $data, string $bidPrice, string $quoteAsset): array
    {
        throw_if($data->quantity === null, RuntimeException::class, 'quantity is required for a SELL MARKET order.');

        $cummulativeQuoteQty = bcmul($data->quantity, $bidPrice, self::LEDGER_SCALE);

        return [$data->quantity, $cummulativeQuoteQty, $bidPrice, $quoteAsset];
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
