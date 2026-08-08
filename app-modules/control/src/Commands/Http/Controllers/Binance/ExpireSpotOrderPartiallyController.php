<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Spot\Actions\ExpireSpotOrderPartially;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/spot-orders/{spotOrder}/expire-partially`.
 */
final readonly class ExpireSpotOrderPartiallyController
{
    public function __construct(private ExpireSpotOrderPartially $expire) {}

    public function __invoke(SpotOrder $spotOrder): JsonResponse
    {
        return response()->json([
            'spotOrder' => ResourceRows::spotOrder($this->expire->handle($spotOrder))->toArray(),
        ]);
    }
}
