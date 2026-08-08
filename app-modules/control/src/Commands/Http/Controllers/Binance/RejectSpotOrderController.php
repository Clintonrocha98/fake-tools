<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Spot\Actions\RejectSpotOrder;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/spot-orders/{spotOrder}/reject`.
 */
final readonly class RejectSpotOrderController
{
    public function __construct(private RejectSpotOrder $reject) {}

    public function __invoke(SpotOrder $spotOrder): JsonResponse
    {
        return response()->json([
            'spotOrder' => ResourceRows::spotOrder($this->reject->handle($spotOrder))->toArray(),
        ]);
    }
}
