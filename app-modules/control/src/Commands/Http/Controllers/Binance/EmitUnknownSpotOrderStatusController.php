<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\Commands\Http\Requests\EmitUnknownStatusRequest;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Spot\Actions\EmitUnknownSpotOrderStatus;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/spot-orders/{spotOrder}/emit-unknown-status`.
 */
final readonly class EmitUnknownSpotOrderStatusController
{
    public function __construct(private EmitUnknownSpotOrderStatus $emit) {}

    public function __invoke(EmitUnknownStatusRequest $request, SpotOrder $spotOrder): JsonResponse
    {
        return response()->json([
            'spotOrder' => ResourceRows::spotOrder($this->emit->handle($spotOrder, $request->rawStatus()))->toArray(),
        ]);
    }
}
