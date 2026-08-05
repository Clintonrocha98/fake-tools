<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Http\Controllers;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Http\Errors\ErrorFamily;
use He4rt\FakeBinance\Http\Errors\ErrorResponseFactory;
use He4rt\FakeBinance\Spot\DTOs\SpotOrderView;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v3/order?symbol=…&origClientOrderId=… — assinado. Consulta
 * SEMPRE por `origClientOrderId` (o id do próprio chamador), nunca por
 * `orderId` — é assim que o monolito consumidor recupera uma ordem, inclusive
 * a original de uma tentativa duplicada. Mesma resposta do POST, sem `fills`.
 */
final readonly class GetOrderController
{
    public function __construct(private ErrorResponseFactory $errors) {}

    public function __invoke(Request $request): JsonResponse
    {
        $family = ErrorFamily::fromPath($request->path());

        $symbol = $request->query('symbol');
        $origClientOrderId = $request->query('origClientOrderId');

        if (!is_string($symbol) || $symbol === '' || !is_string($origClientOrderId) || $origClientOrderId === '') {
            return $this->errors->make($family, BinanceErrorCode::MandatoryParameterMissing);
        }

        $order = SpotOrder::query()
            ->where('symbol', $symbol)
            ->where('client_order_id', $origClientOrderId)
            ->first();

        if (!$order instanceof SpotOrder) {
            return $this->errors->make($family, BinanceErrorCode::NoSuchOrder);
        }

        return response()->json(SpotOrderView::fromModel($order)->toWireArray(withFills: false));
    }
}
