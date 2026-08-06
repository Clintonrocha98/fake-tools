<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\Http\Controllers;

use He4rt\FakeBinance\Withdraw\Actions\GetWithdrawHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /sapi/v1/capital/withdraw/history — assinado (`fake-binance.signed`). Devolve o
 * array de withdrawals honrando todos os filtros documentados: `coin` +
 * `withdrawOrderId` (o par que `treasury:reconcile-offramp-withdraws` usa para
 * localizar o withdraw) mais `status`, `startTime`, `endTime`, `limit` e `offset`.
 */
final readonly class WithdrawHistoryController
{
    public function __construct(private GetWithdrawHistory $history) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rows = $this->history->handle(
            coin: $this->stringOrNull($request, 'coin'),
            withdrawOrderId: $this->stringOrNull($request, 'withdrawOrderId'),
            status: $this->intOrNull($request, 'status'),
            startTime: $this->intOrNull($request, 'startTime'),
            endTime: $this->intOrNull($request, 'endTime'),
            limit: $this->intOrNull($request, 'limit'),
            offset: $this->intOrNull($request, 'offset'),
        );

        return response()->json($rows);
    }

    private function stringOrNull(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function intOrNull(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        return is_numeric($value) ? (int) $value : null;
    }
}
