<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Fiat\Actions\CreditFiatOrderNow;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/fiat-orders/{fiatOrder}/credit` — o `credited_at` da
 * Action é o guard de idempotência: chamar de novo não dobra o saldo.
 */
final readonly class CreditFiatOrderController
{
    public function __construct(private CreditFiatOrderNow $credit) {}

    public function __invoke(FiatOrder $fiatOrder): JsonResponse
    {
        return response()->json([
            'fiatOrder' => ResourceRows::fiatOrder($this->credit->handle($fiatOrder))->toArray(),
        ]);
    }
}
