<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Deposit\Http\Controllers;

use He4rt\FakeBinance\Deposit\Actions\GetCryptoDepositHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /sapi/v1/capital/deposit/hisrec — assinado (`fake-binance.signed`).
 * Todos os filtros são opcionais, como na venue real: sem nenhum, lista tudo.
 */
final readonly class DepositHistoryController
{
    public function __construct(private GetCryptoDepositHistory $history) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rows = $this->history->handle(
            coin: $this->stringOrNull($request, 'coin'),
            network: $this->stringOrNull($request, 'network'),
            status: $this->intOrNull($request, 'status'),
            txId: $this->stringOrNull($request, 'txId'),
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
