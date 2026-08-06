<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Actions;

use He4rt\FakeBinance\Ledger\Support\LedgerAmount;
use He4rt\FakeBinance\Spot\Enums\OrderSide;
use He4rt\FakeBinance\Spot\Enums\SpotSymbol;
use He4rt\FakeBinance\Spot\Models\SpotOrder;

/**
 * GET /api/v3/myTrades: os trades são os fills que o fake já grava — um por
 * ordem executada ({@see SpotOrder::wireFills()}), com o `order_id` como id do
 * trade sintético (o MESMO `tradeId` que a resposta FULL do POST reporta).
 * `commission`/`commissionAsset` saem das colunas da ordem, então batem byte a
 * byte com o que o POST respondeu. O shape por linha é o que o
 * `MyTradesResponse` do monolito consumidor lê.
 */
final readonly class GetMyTrades
{
    /** Default da doc quando o request não informa `limit`. */
    private const int DEFAULT_LIMIT = 500;

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(SpotSymbol $symbol, ?int $orderId = null, ?int $limit = null): array
    {
        $query = SpotOrder::query()
            ->where('symbol', $symbol->value)
            ->where('executed_qty', '>', 0)
            ->whereNotNull('fill_price')
            ->orderBy('order_id');

        if ($orderId !== null) {
            $query->where('order_id', $orderId);
        }

        $trades = $query->get()
            ->map(fn (SpotOrder $order): array => [
                'symbol' => $order->symbol,
                'id' => $order->order_id,
                'orderId' => $order->order_id,
                'orderListId' => -1,
                'price' => LedgerAmount::wire((string) $order->fill_price),
                'qty' => LedgerAmount::wire((string) $order->executed_qty),
                'quoteQty' => LedgerAmount::wire((string) $order->cummulative_quote_qty),
                'commission' => LedgerAmount::wire((string) $order->commission),
                'commissionAsset' => (string) $order->commission_asset,
                'time' => $order->created_at?->getTimestampMs() ?? 0,
                'isBuyer' => $order->side === OrderSide::Buy,
                'isMaker' => false,
                'isBestMatch' => true,
            ])
            ->take(-($limit ?? self::DEFAULT_LIMIT))
            ->values()
            ->all();

        return array_values($trades);
    }
}
