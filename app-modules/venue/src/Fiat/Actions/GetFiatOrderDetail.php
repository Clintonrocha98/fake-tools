<?php

declare(strict_types=1);

namespace He4rt\Venue\Fiat\Actions;

use He4rt\Venue\Fiat\Enums\FiatOrderStatus;
use He4rt\Venue\Fiat\Exceptions\FiatOrderNotFoundException;
use He4rt\Venue\Fiat\Models\FiatOrder;
use He4rt\Venue\Ledger\Actions\CreditLedgerAccount;
use Illuminate\Support\Facades\DB;

/**
 * GET /sapi/v1/fiat/get-order-detail — releitura que ela mesma faz a ordem
 * avançar: LAZY, sem scheduler. Só `Processing` avança sozinho (pela idade
 * contra `venue-fiat.advance_seconds`); todo estado de falha só chega via
 * `forced_status` (override do painel, #7). Ao entrar em
 * {@see FiatOrderStatus::Success} pela PRIMEIRA vez — lazy ou forçado —
 * credita `CreditLedgerAccount` UMA vez; `credited_at` é o guard de
 * idempotência, então uma releitura repetida nunca credita duas vezes.
 */
final readonly class GetFiatOrderDetail
{
    public function __construct(private CreditLedgerAccount $credit) {}

    public function __invoke(string $orderNo): FiatOrder
    {
        $order = FiatOrder::query()->where('order_no', $orderNo)->first();

        if (!$order instanceof FiatOrder) {
            throw FiatOrderNotFoundException::forOrderNo($orderNo);
        }

        return DB::transaction(function () use ($order): FiatOrder {
            /** @var FiatOrder $locked */
            $locked = FiatOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $effective = $locked->forced_status ?? $this->lazyStatus($locked);

            if ($effective !== $locked->status) {
                $locked->update(['status' => $effective]);
            }

            if ($effective->isCredited() && $locked->credited_at === null) {
                ($this->credit)($locked->currency, $locked->amount);
                $locked->update(['credited_at' => now()]);
            }

            return $locked->refresh();
        });
    }

    private function lazyStatus(FiatOrder $order): FiatOrderStatus
    {
        if ($order->status !== FiatOrderStatus::Processing) {
            return $order->status;
        }

        $advanceSeconds = config()->integer('venue-fiat.advance_seconds');
        $age = $order->created_at?->diffInSeconds(now()) ?? 0;

        return $age >= $advanceSeconds ? FiatOrderStatus::Success : FiatOrderStatus::Processing;
    }
}
