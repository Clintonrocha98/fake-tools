<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\Commands\Http\Requests\EmitUnknownStatusRequest;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Withdraw\Actions\EmitUnknownWithdrawStatus;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/withdrawals/{withdrawal}/emit-unknown-status` — o
 * status do saque é numérico na wire, então o override também é.
 */
final readonly class EmitUnknownWithdrawStatusController
{
    public function __construct(private EmitUnknownWithdrawStatus $emit) {}

    public function __invoke(EmitUnknownStatusRequest $request, Withdrawal $withdrawal): JsonResponse
    {
        return response()->json([
            'withdrawal' => ResourceRows::withdrawal($this->emit->handle($withdrawal, $request->rawStatusAsInt()))->toArray(),
        ]);
    }
}
