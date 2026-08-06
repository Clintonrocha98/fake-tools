<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Http\Controllers;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Http\Errors\ErrorFamily;
use He4rt\FakeBinance\Http\Errors\ErrorResponseFactory;
use He4rt\FakeBinance\Spot\Actions\GetBookTicker;
use He4rt\FakeBinance\Spot\Enums\SpotSymbol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v3/ticker/bookTicker — público, sem assinatura.
 */
final readonly class BookTickerController
{
    public function __construct(
        private GetBookTicker $bookTicker,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $symbol = SpotSymbol::tryFromWire($request->query('symbol'));

        if (!$symbol instanceof SpotSymbol) {
            return $this->errors->make(ErrorFamily::fromPath($request->path()), BinanceErrorCode::InvalidSymbol);
        }

        return response()->json($this->bookTicker->handle($symbol)->toWireArray());
    }
}
