<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\Commands\Http\Requests\ForceStatusRequest;
use He4rt\Control\Http\WireEnum;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Fiat\Actions\ForceFiatOrderStatus;
use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/fiat-orders/{fiatOrder}/force`.
 */
final readonly class ForceFiatOrderStatusController
{
    public function __construct(private ForceFiatOrderStatus $force) {}

    public function __invoke(ForceStatusRequest $request, FiatOrder $fiatOrder): JsonResponse
    {
        $status = WireEnum::resolve(FiatOrderStatus::class, $request->status());

        return response()->json([
            'fiatOrder' => ResourceRows::fiatOrder($this->force->handle($fiatOrder, $status))->toArray(),
        ]);
    }
}
