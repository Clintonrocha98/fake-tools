<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Http\Controllers;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Http\Errors\ErrorFamily;
use He4rt\FakeBinance\Http\Errors\ErrorResponseFactory;
use He4rt\FakeBinance\Spot\Actions\GetMyTrades;
use He4rt\FakeBinance\Spot\Enums\SpotSymbol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v3/myTrades — assinado (`fake-binance.signed`). Filtra por `symbol`
 * (obrigatório) e opcionalmente `orderId`, como o `GetMyTradesRequest` do
 * monolito consumidor emite.
 */
final readonly class MyTradesController
{
    public function __construct(
        private GetMyTrades $myTrades,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $family = ErrorFamily::fromPath($request->path());

        $symbolValue = $request->query('symbol');

        if (!is_string($symbolValue) || $symbolValue === '') {
            return $this->errors->make($family, BinanceErrorCode::MandatoryParameterMissing);
        }

        $symbol = SpotSymbol::tryFromWire($symbolValue);

        if (!$symbol instanceof SpotSymbol) {
            return $this->errors->make($family, BinanceErrorCode::InvalidSymbol);
        }

        $orderId = $request->query('orderId');
        $limit = $request->query('limit');

        return response()->json($this->myTrades->handle(
            $symbol,
            is_numeric($orderId) ? (int) $orderId : null,
            is_numeric($limit) ? (int) $limit : null,
        ));
    }
}
