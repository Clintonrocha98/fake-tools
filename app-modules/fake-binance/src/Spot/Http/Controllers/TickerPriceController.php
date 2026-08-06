<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Http\Controllers;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Http\Errors\ErrorFamily;
use He4rt\FakeBinance\Http\Errors\ErrorResponseFactory;
use He4rt\FakeBinance\Spot\Actions\GetTickerPrice;
use He4rt\FakeBinance\Spot\Enums\SpotSymbol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v3/ticker/price — público, sem assinatura. Exige `symbol`, como
 * {@see BookTickerController}: um symbol ausente nunca cai num par default.
 */
final readonly class TickerPriceController
{
    public function __construct(
        private GetTickerPrice $tickerPrice,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $symbol = SpotSymbol::tryFromWire($request->query('symbol'));

        if (!$symbol instanceof SpotSymbol) {
            return $this->errors->make(ErrorFamily::fromPath($request->path()), BinanceErrorCode::InvalidSymbol);
        }

        return response()->json($this->tickerPrice->handle($symbol));
    }
}
