<?php

declare(strict_types=1);

namespace He4rt\Venue\Withdraw\Http\Controllers;

use He4rt\Venue\Withdraw\Actions\GetWithdrawHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /sapi/v1/capital/withdraw/history — assinado (`venue.signed`). Devolve o
 * array de withdrawals filtrado por `coin` + `withdrawOrderId`, o par que
 * `treasury:reconcile-offramp-withdraws` usa para localizar o withdraw.
 */
final readonly class WithdrawHistoryController
{
    public function __construct(private GetWithdrawHistory $history) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rows = ($this->history)(
            coin: $this->stringOrNull($request, 'coin'),
            withdrawOrderId: $this->stringOrNull($request, 'withdrawOrderId'),
        );

        return response()->json($rows);
    }

    private function stringOrNull(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
