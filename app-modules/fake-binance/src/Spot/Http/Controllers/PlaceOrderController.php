<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Http\Controllers;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Http\Errors\ErrorFamily;
use He4rt\FakeBinance\Http\Errors\ErrorResponseFactory;
use He4rt\FakeBinance\Ledger\Exceptions\InsufficientLedgerBalanceException;
use He4rt\FakeBinance\Spot\Actions\PlaceMarketOrder;
use He4rt\FakeBinance\Spot\DTOs\PlaceMarketOrderData;
use He4rt\FakeBinance\Spot\DTOs\SpotOrderView;
use He4rt\FakeBinance\Spot\Enums\OrderSide;
use He4rt\FakeBinance\Spot\Enums\SpotSymbol;
use He4rt\FakeBinance\Spot\Exceptions\DuplicateClientOrderIdException;
use He4rt\FakeBinance\Spot\Exceptions\SpotFilterViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v3/order — assinado, só MARKET. Valida os parâmetros antes de
 * qualquer execução, cada rejeição no código da Binance real (parâmetro
 * mandatório ausente, `symbol`/`type`/`side` inválidos); saldo insuficiente
 * e `newClientOrderId` duplicado colapsam no mesmo -2010 (NEW_ORDER_REJECTED)
 * que a Binance real usa para ambos os casos.
 */
final readonly class PlaceOrderController
{
    public function __construct(
        private PlaceMarketOrder $placeOrder,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $family = ErrorFamily::fromPath($request->path());

        $data = $this->validated($request, $family);

        if ($data instanceof JsonResponse) {
            return $data;
        }

        try {
            $order = $this->placeOrder->handle($data);
        } catch (DuplicateClientOrderIdException|InsufficientLedgerBalanceException $exception) {
            return $this->errors->make($family, BinanceErrorCode::NewOrderRejected, $exception->getMessage());
        } catch (SpotFilterViolationException $exception) {
            return $this->errors->make($family, BinanceErrorCode::FilterFailure, $exception->getMessage());
        }

        return response()->json(SpotOrderView::fromModel($order)->toWireArray(withFills: true));
    }

    private function validated(Request $request, ErrorFamily $family): PlaceMarketOrderData|JsonResponse
    {
        $symbol = $request->query('symbol');
        $sideValue = $request->query('side');
        $type = $request->query('type');
        $newClientOrderId = $request->query('newClientOrderId');
        $quoteOrderQty = $request->query('quoteOrderQty');
        $quantity = $request->query('quantity');

        if (!is_string($symbol) || $symbol === '' || !is_string($newClientOrderId) || $newClientOrderId === '') {
            return $this->errors->make($family, BinanceErrorCode::MandatoryParameterMissing);
        }

        if (!SpotSymbol::tryFromWire($symbol) instanceof SpotSymbol) {
            return $this->errors->make($family, BinanceErrorCode::InvalidSymbol);
        }

        if ($type !== null && $type !== 'MARKET') {
            return $this->errors->make($family, BinanceErrorCode::InvalidOrderType);
        }

        $side = is_string($sideValue) ? OrderSide::tryFrom($sideValue) : null;

        if (!$side instanceof OrderSide) {
            return $this->errors->make($family, BinanceErrorCode::InvalidSide);
        }

        if ($side === OrderSide::Buy && !is_numeric($quoteOrderQty)) {
            return $this->errors->make($family, BinanceErrorCode::MandatoryParameterMissing, 'quoteOrderQty is required for a BUY MARKET order.');
        }

        if ($side === OrderSide::Sell && !is_numeric($quantity)) {
            return $this->errors->make($family, BinanceErrorCode::MandatoryParameterMissing, 'quantity is required for a SELL MARKET order.');
        }

        return new PlaceMarketOrderData(
            symbol: $symbol,
            side: $side,
            newClientOrderId: $newClientOrderId,
            quoteOrderQty: is_numeric($quoteOrderQty) ? (string) $quoteOrderQty : null,
            quantity: is_numeric($quantity) ? (string) $quantity : null,
        );
    }
}
