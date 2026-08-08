<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\Commands\Http\Requests\SetFrozenRequest;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Fiat\Actions\SetFiatOrderFrozen;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/fiat-orders/{fiatOrder}/freeze`.
 */
final readonly class SetFiatOrderFrozenController
{
    public function __construct(private SetFiatOrderFrozen $freeze) {}

    public function __invoke(SetFrozenRequest $request, FiatOrder $fiatOrder): JsonResponse
    {
        return response()->json([
            'fiatOrder' => ResourceRows::fiatOrder($this->freeze->handle($fiatOrder, $request->frozen()))->toArray(),
        ]);
    }
}
