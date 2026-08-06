<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\DTOs;

use He4rt\FakeBinance\Ledger\Support\LedgerAmount;
use He4rt\FakeBinance\Spot\Models\SpotOrder;

/**
 * O shape FULL completo da wire, não só o subconjunto que
 * `SpotOrderResponse::fromResponse()` do monolito consumidor lê — um campo que
 * o consumidor passe a ler amanhã já existe aqui. `fills` e `transactTime` só
 * aparecem no POST (execução); os campos de releitura (`time`, `updateTime`,
 * `isWorking`) só no GET — a mesma distinção da venue real ({@see toWireArray()}
 * a recebe como parâmetro). `status` ecoa `raw_status_override` quando setado —
 * vocabulário fora de `OrderStatus`, cenário do painel para provar o
 * fail-closed do monolito consumidor.
 *
 * Onde o fake não tem o conceito, o valor constante da doc: `price` de uma
 * MARKET é sempre `0.00000000`, `timeInForce` GTC, `orderListId` -1,
 * `selfTradePreventionMode` NONE.
 */
final readonly class SpotOrderView
{
    private function __construct(private SpotOrder $order) {}

    public static function fromModel(SpotOrder $order): self
    {
        return new self($order);
    }

    /**
     * @return array<string, mixed>
     */
    public function toWireArray(bool $withFills): array
    {
        $createdAtMs = $this->order->created_at?->getTimestampMs() ?? 0;

        $payload = [
            'symbol' => $this->order->symbol,
            'orderId' => $this->order->order_id,
            'orderListId' => -1,
            'clientOrderId' => $this->order->client_order_id,
            'price' => '0.00000000',
            'origQty' => LedgerAmount::wire((string) ($this->order->quantity ?? $this->order->executed_qty)),
            'origQuoteOrderQty' => LedgerAmount::wire((string) ($this->order->quote_order_qty ?? '0')),
            'executedQty' => LedgerAmount::wire((string) $this->order->executed_qty),
            'cummulativeQuoteQty' => LedgerAmount::wire((string) $this->order->cummulative_quote_qty),
            'status' => $this->order->raw_status_override ?? $this->order->status->value,
            'timeInForce' => 'GTC',
            'type' => $this->order->type,
            'side' => $this->order->side->value,
            'workingTime' => $createdAtMs,
            'selfTradePreventionMode' => 'NONE',
        ];

        if ($withFills) {
            $payload['transactTime'] = $createdAtMs;
            $payload['fills'] = $this->order->wireFills();

            return $payload;
        }

        return [
            ...$payload,
            'stopPrice' => '0.00000000',
            'icebergQty' => '0.00000000',
            'time' => $createdAtMs,
            'updateTime' => $this->order->updated_at?->getTimestampMs() ?? $createdAtMs,
            'isWorking' => true,
        ];
    }
}
