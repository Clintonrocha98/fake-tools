<?php

declare(strict_types=1);

namespace He4rt\Venue\Ledger\Http\Controllers;

use He4rt\Venue\Ledger\Actions\GetAccountSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v3/account — a assinatura é verificada pelo middleware `venue.signed`
 * na definição da rota, não aqui.
 */
final readonly class AccountController
{
    public function __construct(private GetAccountSnapshot $snapshot) {}

    public function __invoke(Request $request): JsonResponse
    {
        $snapshot = $this->snapshot->handle(omitZeroBalances: $request->boolean('omitZeroBalances'));

        return response()->json($snapshot);
    }
}
