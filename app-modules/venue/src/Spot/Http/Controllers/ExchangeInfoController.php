<?php

declare(strict_types=1);

namespace He4rt\Venue\Spot\Http\Controllers;

use He4rt\Venue\Spot\Actions\GetExchangeInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v3/exchangeInfo — público, sem assinatura.
 */
final readonly class ExchangeInfoController
{
    public function __construct(private GetExchangeInfo $exchangeInfo) {}

    public function __invoke(Request $request): JsonResponse
    {
        $symbol = (string) $request->query('symbol');

        return response()->json([
            'symbols' => [($this->exchangeInfo)($symbol)->toWireArray()],
        ]);
    }
}
