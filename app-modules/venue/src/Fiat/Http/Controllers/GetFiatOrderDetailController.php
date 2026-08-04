<?php

declare(strict_types=1);

namespace He4rt\Venue\Fiat\Http\Controllers;

use He4rt\Venue\Fiat\Actions\GetFiatOrderDetail;
use He4rt\Venue\Fiat\Enums\FiatStatusDialect;
use He4rt\Venue\Fiat\Exceptions\FiatOrderNotFoundException;
use He4rt\Venue\Fiat\Models\FiatOrder;
use He4rt\Venue\Http\Errors\ErrorFamily;
use He4rt\Venue\Http\Errors\VenueErrorResponseFactory;
use He4rt\Venue\Ledger\Support\LedgerAmount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /sapi/v1/fiat/get-order-detail?orderNo=… — a leitura que faz a ordem
 * avançar ({@see GetFiatOrderDetail}). `status` e `orderStatus` saem com o
 * mesmo valor: o monolito consumidor lê qualquer um dos dois indistintamente,
 * e a Binance real já foi observada respondendo cada um deles em momentos
 * diferentes — o fake nunca aposta em só um.
 */
final readonly class GetFiatOrderDetailController
{
    public function __construct(
        private GetFiatOrderDetail $getFiatOrderDetail,
        private VenueErrorResponseFactory $errors,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['orderNo' => ['required', 'string']]);

        $orderNo = $request->string('orderNo')->toString();

        try {
            $order = ($this->getFiatOrderDetail)($orderNo);
        } catch (FiatOrderNotFoundException $fiatOrderNotFoundException) {
            return $this->errors->make(ErrorFamily::Fiat, $fiatOrderNotFoundException->errorCode, $fiatOrderNotFoundException->getMessage());
        }

        $dialect = FiatStatusDialect::from(config()->string('venue-fiat.status_dialect', 'live'));
        $status = $order->effectiveStatus()->toWire($dialect);

        return response()->json([
            'code' => '000000',
            'message' => 'success',
            'data' => $this->orderData($order, $status),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderData(FiatOrder $order, string $status): array
    {
        return [
            'orderNo' => $order->order_no,
            'status' => $status,
            'orderStatus' => $status,
            'currency' => $order->currency,
            'amount' => LedgerAmount::wire($order->amount),
            'pixcode' => $order->brcode,
        ];
    }
}
