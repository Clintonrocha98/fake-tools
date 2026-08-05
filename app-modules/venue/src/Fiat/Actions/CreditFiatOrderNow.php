<?php

declare(strict_types=1);

namespace He4rt\Venue\Fiat\Actions;

use He4rt\Venue\Fiat\Enums\FiatOrderStatus;
use He4rt\Venue\Fiat\Models\FiatOrder;
use He4rt\Venue\Ledger\Actions\CreditLedgerAccount;
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

            $locked->update([
                'status' => FiatOrderStatus::Success,
                'forced_status' => null,
                'forced_wire_status' => null,
            ]);

            if ($locked->credited_at === null) {
                $this->credit->handle($locked->currency, $locked->amount);
                $locked->update(['credited_at' => now()]);
            }

            return $locked->refresh();
        });
    }
}
