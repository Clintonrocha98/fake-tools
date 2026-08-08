<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Withdraw\Actions\CompleteWithdrawNow;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/withdrawals/{withdrawal}/complete`.
 */
final readonly class CompleteWithdrawNowController
{
    public function __construct(private CompleteWithdrawNow $complete) {}

    public function __invoke(Withdrawal $withdrawal): JsonResponse
    {
        return response()->json([
            'withdrawal' => ResourceRows::withdrawal($this->complete->handle($withdrawal))->toArray(),
        ]);
    }
}
