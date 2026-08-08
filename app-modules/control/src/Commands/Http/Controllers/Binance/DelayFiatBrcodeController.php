<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\Commands\Http\Requests\DelayFiatBrcodeRequest;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Fiat\Actions\DelayFiatBrcode;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/fiat-orders/{fiatOrder}/delay-brcode` — `reads`
 * omitido restaura o default (brcode imediato).
 */
final readonly class DelayFiatBrcodeController
{
    public function __construct(private DelayFiatBrcode $delay) {}

    public function __invoke(DelayFiatBrcodeRequest $request, FiatOrder $fiatOrder): JsonResponse
    {
        return response()->json([
            'fiatOrder' => ResourceRows::fiatOrder($this->delay->handle($fiatOrder, $request->reads()))->toArray(),
        ]);
    }
}
