<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Actions;

use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Exceptions\FiatOrderNotFoundException;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use Illuminate\Support\Facades\DB;

/**
 * GET /sapi/v1/fiat/get-order-detail — releitura que ela mesma faz a ordem
 * avançar: LAZY, sem scheduler. Só `Processing` avança sozinho (pela idade
 * contra `fake-binance-fiat.advance_seconds`); todo estado de falha só chega via
 * `forced_status` (override do painel). `forced_status` é uma máscara de
 * leitura — nunca é gravado em `status` — para que limpar o override deixe o
 * avanço lazy retomar de onde `status` estava. Ao entrar em
 * {@see FiatOrderStatus::Success} pela PRIMEIRA vez — lazy ou forçado —
 * credita `CreditLedgerAccount` UMA vez; `credited_at` é o guard de
 * idempotência, então uma releitura repetida nunca credita duas vezes.
 * `forced_wire_status` cobre o vocabulário de wire que `FiatOrderStatus` não
 * modela: enquanto setado, a ordem nunca avança e nunca credita. Toda releitura
 * conta como uma leitura de brcode (`brcode_reads_count`), independente do
 * congelamento — {@see FiatOrder::brcodeVisible()} é
 * quem decide, a partir dessa contagem, se o controller deve expor `pixcode`.
 */
final readonly class GetFiatOrderDetail
{
    public function __construct(private CreditLedgerAccount $credit) {}

    public function handle(string $orderNo): FiatOrder
    {
        $order = FiatOrder::query()->where('order_no', $orderNo)->first();

        if (!$order instanceof FiatOrder) {
            throw FiatOrderNotFoundException::forOrderNo($orderNo);
        }

        return DB::transaction(function () use ($order): FiatOrder {
            /** @var FiatOrder $locked */
            $locked = FiatOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $locked->increment('brcode_reads_count');

            if ($locked->forced_wire_status !== null) {
                return $locked;
            }

            // Congelada: o avanço lazy não roda, `status` fica exatamente onde estava.
            if ($locked->forced_status === null && !$locked->frozen) {
                $lazy = $this->lazyStatus($locked);

                if ($lazy !== $locked->status) {
                    $locked->update(['status' => $lazy]);
                }
            }

            $effective = $locked->effectiveStatus();

            if ($effective->isCredited() && $locked->credited_at === null) {
                $this->credit->handle($locked->currency, $locked->amount);
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

        // `config()->integer()` exige um `int` estrito e explode em qualquer outra
        // coisa — inclusive a numeric-string que `env()` produz a partir do `.env`
        // real. O cast manual aceita a mesma faixa de valores que o config file já
        // normaliza para `int`.
        $advanceSeconds = (int) config('fake-binance-fiat.advance_seconds', 60);
        $age = $order->created_at?->diffInSeconds(now()) ?? 0;

        return $age >= $advanceSeconds ? FiatOrderStatus::Success : FiatOrderStatus::Processing;
    }
}
