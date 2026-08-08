<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\Commands\Http\Requests\ForceStatusRequest;
use He4rt\Control\Http\WireEnum;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Withdraw\Actions\ForceWithdrawStatus;
use He4rt\FakeBinance\Withdraw\Enums\WithdrawStatus;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/withdrawals/{withdrawal}/force` — `status` é o enum de
 * INT da wire da Binance (6 = Completed); `info` é o motivo textual que o saque
 * carrega.
 */
final readonly class ForceWithdrawStatusController
{
    public function __construct(private ForceWithdrawStatus $force) {}

    public function __invoke(ForceStatusRequest $request, Withdrawal $withdrawal): JsonResponse
    {
        $status = WireEnum::resolve(WithdrawStatus::class, $request->status());

        return response()->json([
            'withdrawal' => ResourceRows::withdrawal($this->force->handle($withdrawal, $status, $request->info()))->toArray(),
        ]);
    }
}
