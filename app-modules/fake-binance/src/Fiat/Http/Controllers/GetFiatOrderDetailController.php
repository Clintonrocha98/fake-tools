<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Http\Controllers;

use He4rt\FakeBinance\Fiat\Actions\GetFiatOrderDetail;
use He4rt\FakeBinance\Fiat\Enums\BrcodePlacement;
use He4rt\FakeBinance\Fiat\Enums\FiatStatusDialect;
use He4rt\FakeBinance\Fiat\Exceptions\FiatOrderNotFoundException;
use He4rt\FakeBinance\Fiat\Http\Requests\GetFiatOrderDetailRequest;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Http\Errors\ErrorFamily;
use He4rt\FakeBinance\Http\Errors\ErrorResponseFactory;
use Illuminate\Http\JsonResponse;

/**
 * GET /sapi/v1/fiat/get-order-detail?orderNo=… — a leitura que faz a ordem
 * avançar ({@see GetFiatOrderDetail}). Onde a doc e a observação ao vivo dão
 * nomes diferentes ao mesmo dado, o fake serve os DOIS com o mesmo valor —
 * `status`/`orderStatus`, `orderNo`/`orderId`, `fee`/`totalFee` —, nunca
 * apostando em só um. Ver ADR-0004 para o shape completo e o `ext`.
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

        // O brcode só faz sentido enquanto o depósito pode ainda ser pago ou já
        // foi (some quando a ordem morre num estado terminal de falha), E só
        // depois de `brcode_delay_reads` releituras (atraso do painel).
        $brcode = ($pending || $credited) && $order->brcodeVisible() ? $order->brcode : null;

        // Um vocabulário de wire fora do enum não tem motivo de falha conhecido:
        // o `forced_wire_status` existe justamente para o fake não saber o que
        // aquele estado significa.
        $failure = $order->forced_wire_status === null ? $order->effectiveStatus() : null;

        $placement = BrcodePlacement::from(config()->string('fake-binance-fiat.brcode_placement', BrcodePlacement::Root->value));

        $data = [
            'orderNo' => $order->order_no,
            // A doc lista `orderId`; o vivo entrega `orderNo`. Servir os dois com
            // o mesmo valor é a disciplina de `status`/`orderStatus`: o fake
            // nunca aposta em só um nome.
            'orderId' => $order->order_no,
            'status' => $status,
            'orderStatus' => $status,
            'fiatCurrency' => $order->currency,
            'currency' => $order->currency,
            'amount' => bcadd((string) $order->amount, '0', 2),
            'method' => $order->payment_method,
            'fee' => '0.00',
            'totalFee' => '0.00',
            'errorCode' => $failure?->errorCode(),
            'errorMessage' => $failure?->errorMessage(),
            'createTime' => $order->created_at?->getTimestampMs(),
            'updateTime' => $order->updated_at?->getTimestampMs(),
        ];

        if ($placement === BrcodePlacement::Root) {
            $data['pixcode'] = $brcode;
        }

        $data['ext'] = $this->ext($order, $placement, $brcode);

        return $data;
    }

    /**
     * O `ext` (OBJECT) que a doc lista e o fake nunca serviu. No placement
     * `ext`, é ele — e só ele — que carrega o brcode: a raiz fica sem `pixcode`,
     * então apenas a varredura recursiva do consumidor o encontra.
     *
     * @return array<string, mixed>
     */
    private function ext(FiatOrder $order, BrcodePlacement $placement, ?string $brcode): array
    {
        $ext = ['paymentMethod' => $order->payment_method];

        if ($placement === BrcodePlacement::Ext) {
            $ext['pixCode'] = $brcode;
        }

        return $ext;
    }
}
