<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\Commands\Http\Requests\EmitUnknownStatusRequest;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Fiat\Actions\EmitUnknownFiatWireStatus;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/fiat-orders/{fiatOrder}/emit-unknown-status` — o
 * vocabulário de wire que `FiatOrderStatus` não modela. Enquanto setado, a
 * ordem nunca avança e nunca credita.
 */
final readonly class EmitUnknownFiatWireStatusController
{
    public function __construct(private EmitUnknownFiatWireStatus $emit) {}

    public function __invoke(EmitUnknownStatusRequest $request, FiatOrder $fiatOrder): JsonResponse
    {
        return response()->json([
            'fiatOrder' => ResourceRows::fiatOrder($this->emit->handle($fiatOrder, $request->rawStatus()))->toArray(),
        ]);
    }
}
