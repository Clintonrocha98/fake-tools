<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Http\Controllers;

use He4rt\FakeBinance\Fiat\Actions\GetFiatOrderDetail;
use He4rt\FakeBinance\Fiat\Enums\FiatStatusDialect;
use He4rt\FakeBinance\Fiat\Exceptions\FiatOrderNotFoundException;
use He4rt\FakeBinance\Fiat\Http\Requests\GetFiatOrderDetailRequest;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Http\Errors\ErrorFamily;
use He4rt\FakeBinance\Http\Errors\ErrorResponseFactory;
use Illuminate\Http\JsonResponse;

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
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(GetFiatOrderDetailRequest $request): JsonResponse
    {
        $orderNo = $request->string('orderNo')->toString();

        try {
            $order = $this->getFiatOrderDetail->handle($orderNo);
        } catch (FiatOrderNotFoundException $fiatOrderNotFoundException) {
            return $this->errors->make(ErrorFamily::Fiat, $fiatOrderNotFoundException->errorCode, $fiatOrderNotFoundException->getMessage());
        }

        $dialect = FiatStatusDialect::from(config()->string('fake-binance-fiat.status_dialect', 'live'));

        // Um vocabulário de wire fora do enum (`forced_wire_status`) é ecoado
        // verbatim — o fail-closed do consumidor é o arm que este campo existe
        // para exercitar, então o fake nunca o traduz para um caso conhecido.
        $status = $order->forced_wire_status ?? $order->effectiveStatus()->toWire($dialect);

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
        $pending = $order->forced_wire_status !== null || $order->effectiveStatus()->isPending();
        $credited = $order->forced_wire_status === null && $order->effectiveStatus()->isCredited();

        return [
            'orderNo' => $order->order_no,
            'status' => $status,
            'orderStatus' => $status,
            'fiatCurrency' => $order->currency,
            'currency' => $order->currency,
            'amount' => bcadd((string) $order->amount, '0', 2),
            'method' => $order->payment_method,
            'totalFee' => '0.00',
            'createTime' => $order->created_at?->getTimestampMs(),
            'updateTime' => $order->updated_at?->getTimestampMs(),
            // O brcode só faz sentido enquanto o depósito pode ainda ser pago
            // ou já foi (some quando a ordem morre num estado terminal de falha),
            // E só depois de `brcode_delay_reads` releituras (atraso do painel).
            'pixcode' => ($pending || $credited) && $order->brcodeVisible() ? $order->brcode : null,
        ];
    }
}
