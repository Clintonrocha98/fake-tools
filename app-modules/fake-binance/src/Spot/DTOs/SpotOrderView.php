<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\DTOs;

use He4rt\FakeBinance\Ledger\Support\LedgerAmount;
use He4rt\FakeBinance\Spot\Models\SpotOrder;

/**
 * O shape FULL que `SpotOrderResponse::fromResponse()` do monolito consumidor
 * lê — `fills` só aparece no POST (execução), nunca no GET de releitura
 * ({@see toWireArray()} recebe essa distinção como parâmetro). `status` ecoa
 * `raw_status_override` quando setado — vocabulário fora de `OrderStatus`,
 * cenário do painel para provar o fail-closed do monolito consumidor.
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
        $payload = [
            'symbol' => $this->order->symbol,
            'orderId' => $this->order->order_id,
            'clientOrderId' => $this->order->client_order_id,
            'status' => $this->order->raw_status_override ?? $this->order->status->value,
            'side' => $this->order->side->value,
            'type' => $this->order->type,
            'executedQty' => LedgerAmount::wire((string) $this->order->executed_qty),
            'cummulativeQuoteQty' => LedgerAmount::wire((string) $this->order->cummulative_quote_qty),
        ];

        if ($withFills) {
            $payload['fills'] = $this->order->wireFills();
        }

        return $payload;
    }
}
