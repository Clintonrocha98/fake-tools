<?php

declare(strict_types=1);

namespace He4rt\Venue\Spot\Http\Controllers;

use He4rt\Venue\Spot\Actions\GetBookTicker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v3/ticker/bookTicker — público, sem assinatura.
 */
final readonly class BookTickerController
{
    public function __construct(private GetBookTicker $bookTicker) {}

    public function __invoke(Request $request): JsonResponse
    {
        $symbol = (string) $request->query('symbol');

        return response()->json(($this->bookTicker)($symbol)->toWireArray());
    }
}
