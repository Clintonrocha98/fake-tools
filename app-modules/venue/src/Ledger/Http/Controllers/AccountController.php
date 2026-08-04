<?php

declare(strict_types=1);

namespace He4rt\Venue\Ledger\Http\Controllers;

use He4rt\Venue\Ledger\Actions\GetAccountSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v3/account — sem middleware de assinatura aqui de propósito: o acoplamento
 * com `venue.signed` acontece no merge da onda (ticket #2), não neste ticket.
 */
final readonly class AccountController
{
    public function __construct(private GetAccountSnapshot $snapshot) {}

    public function __invoke(Request $request): JsonResponse
    {
        $snapshot = ($this->snapshot)(
            omitZeroBalances: $request->boolean('omitZeroBalances'),
        );

        return response()->json($snapshot);
    }
}
