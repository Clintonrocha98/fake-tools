<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Actions;

use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Support\BinanceLog;
use Illuminate\Support\Facades\DB;

/**
 * Cenário do painel: "creditar agora", pulando o relógio do avanço lazy — limpa
 * qualquer override (`forced_status`/`forced_wire_status`) e leva `status`
 * direto a {@see FiatOrderStatus::Success}. `credited_at` continua sendo o
 * guard de idempotência: uma ordem já creditada nunca credita de novo.
 */
final readonly class CreditFiatOrderNow
{
    public function __construct(private CreditLedgerAccount $credit = new CreditLedgerAccount) {}

    public function handle(FiatOrder $order): FiatOrder
    {
        return DB::transaction(function () use ($order): FiatOrder {
            /** @var FiatOrder $locked */
            $locked = FiatOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $from = $locked->status;

            $locked->update([
                'status' => FiatOrderStatus::Success,
                'forced_status' => null,
                'forced_wire_status' => null,
            ]);

            BinanceLog::info('fake-binance.fiat: crédito forçado por cenário — o relógio do avanço lazy é ignorado de propósito, levando a ordem direto a Success', [
                'order_no' => $locked->order_no,
                'from' => $from->value,
                'to' => FiatOrderStatus::Success->value,
            ]);

            if ($locked->credited_at === null) {
                $this->credit->handle($locked->currency, $locked->amount);
                $locked->update(['credited_at' => now()]);

                BinanceLog::info('fake-binance.fiat: ledger creditado sob comando — o guard `credited_at` impede um segundo crédito em releituras futuras', [
                    'order_no' => $locked->order_no,
                    'currency' => $locked->currency,
                    'amount' => $locked->amount,
                ]);
            } else {
                BinanceLog::info('fake-binance.fiat: crédito ignorado — ordem já creditada antes, e creditar de novo dobraria o saldo', [
                    'order_no' => $locked->order_no,
                    'credited_at' => $locked->credited_at->toIso8601String(),
                ]);
            }

            return $locked->refresh();
        });
    }
}
