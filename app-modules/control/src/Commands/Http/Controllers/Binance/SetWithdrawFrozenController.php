<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\Commands\Http\Requests\SetFrozenRequest;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Withdraw\Actions\SetWithdrawFrozen;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/withdrawals/{withdrawal}/freeze`.
 */
final readonly class SetWithdrawFrozenController
{
    public function __construct(private SetWithdrawFrozen $freeze) {}

    public function __invoke(SetFrozenRequest $request, Withdrawal $withdrawal): JsonResponse
    {
        return response()->json([
            'withdrawal' => ResourceRows::withdrawal($this->freeze->handle($withdrawal, $request->frozen()))->toArray(),
        ]);
    }
}
