<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Actions;

use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Exceptions\FiatOrderNotFoundException;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Support\BinanceLog;
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
            BinanceLog::warning('fake-binance.fiat: releitura de order_no que este fake nunca criou — 404 em vez de ordem vazia, que o consumidor leria como resposta malformada', [
                'order_no' => $orderNo,
            ]);

            throw FiatOrderNotFoundException::forOrderNo($orderNo);
        }

        return DB::transaction(function () use ($order): FiatOrder {
            /** @var FiatOrder $locked */
            $locked = FiatOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $locked->increment('brcode_reads_count');

            if ($locked->forced_wire_status !== null) {
                BinanceLog::debug('fake-binance.fiat: avanço lazy pulado — override de wire arbitrário ativo, enquanto setado a ordem nunca avança e nunca credita', [
                    'order_no' => $locked->order_no,
                    'forced_wire_status' => $locked->forced_wire_status,
                ]);

                return $locked;
            }

            // Congelada: o avanço lazy não roda, `status` fica exatamente onde estava.
            if ($locked->forced_status === null && !$locked->frozen) {
                $lazy = $this->lazyStatus($locked);

                if ($lazy !== $locked->status) {
                    $from = $locked->status;

                    $locked->update(['status' => $lazy]);

                    BinanceLog::info('fake-binance.fiat: status avançado na leitura — o fake não tem scheduler, então é o próprio GET do consumidor que faz o tempo passar', [
                        'order_no' => $locked->order_no,
                        'from' => $from->value,
                        'to' => $lazy->value,
                    ]);
                }
            } elseif ($locked->forced_status !== null) {
                BinanceLog::debug('fake-binance.fiat: avanço lazy pulado — override de status ativo, a wire responde o valor forçado até o painel limpá-lo', [
                    'order_no' => $locked->order_no,
                    'forced_status' => $locked->forced_status->value,
                ]);
            } else {
                BinanceLog::debug('fake-binance.fiat: avanço lazy pulado — ordem congelada por cenário, o estado fica exatamente onde o operador o deixou', [
                    'order_no' => $locked->order_no,
                    'status' => $locked->status->value,
                ]);
            }

            $effective = $locked->effectiveStatus();

            if ($effective->isCredited() && $locked->credited_at === null) {
                $this->credit->handle($locked->currency, $locked->amount);
                $locked->update(['credited_at' => now()]);

                BinanceLog::info('fake-binance.fiat: ledger creditado na leitura — a ordem entrou em status creditável e o guard `credited_at` impede um segundo crédito em releituras futuras', [
                    'order_no' => $locked->order_no,
                    'currency' => $locked->currency,
                    'amount' => $locked->amount,
                    'effective_status' => $effective->value,
                ]);
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
