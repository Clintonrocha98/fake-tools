<?php

declare(strict_types=1);

namespace He4rt\Venue\Spot\Http\Controllers;

use He4rt\Venue\Http\Errors\BinanceErrorCode;
use He4rt\Venue\Http\Errors\ErrorFamily;
use He4rt\Venue\Http\Errors\VenueErrorResponseFactory;
use He4rt\Venue\Spot\Actions\GetExchangeInfo;
use He4rt\Venue\Spot\Enums\SpotSymbol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v3/exchangeInfo — público, sem assinatura.
 */
final readonly class ExchangeInfoController
{
    public function __construct(
        private GetExchangeInfo $exchangeInfo,
        private VenueErrorResponseFactory $errors,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $symbol = SpotSymbol::tryFromWire($request->query('symbol'));

        if (!$symbol instanceof SpotSymbol) {
            return $this->errors->make(ErrorFamily::fromPath($request->path()), BinanceErrorCode::InvalidSymbol);
        }

        return response()->json([
            'symbols' => [$this->exchangeInfo->handle($symbol->value)->toWireArray()],
        ]);
    }
}
