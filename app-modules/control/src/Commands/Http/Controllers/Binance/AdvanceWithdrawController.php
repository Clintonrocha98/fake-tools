<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Binance;

use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Withdraw\Actions\AdvanceWithdrawStatus;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/binance/withdrawals/{withdrawal}/advance` — o mesmo avanço
 * lazy que `GetWithdrawHistory` dispara em cada saque da página, aqui sob
 * comando e sobre um saque só.
 */
final readonly class AdvanceWithdrawController
{
    public function __construct(private AdvanceWithdrawStatus $advance) {}

    public function __invoke(Withdrawal $withdrawal): JsonResponse
    {
        return response()->json([
            'withdrawal' => ResourceRows::withdrawal($this->advance->handle($withdrawal))->toArray(),
        ]);
    }
}
