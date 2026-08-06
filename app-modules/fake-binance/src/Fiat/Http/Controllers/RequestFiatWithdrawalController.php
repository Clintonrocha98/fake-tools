<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Http\Controllers;

use He4rt\FakeBinance\Fiat\Actions\RequestFiatWithdrawal;
use He4rt\FakeBinance\Fiat\DTOs\FiatWithdrawalData;
use He4rt\FakeBinance\Fiat\Exceptions\FiatWithdrawRefusedException;
use He4rt\FakeBinance\Fiat\Http\Requests\RequestFiatWithdrawalRequest;
use He4rt\FakeBinance\Http\Errors\ErrorFamily;
use He4rt\FakeBinance\Http\Errors\ErrorResponseFactory;
use Illuminate\Http\JsonResponse;

/**
 * POST /sapi/v2/fiat/withdraw — sucesso é `{code: "000000", message, data:
 * {orderId}}` (o `FiatWithdrawalResponse` do monolito lê `data.orderId` com
 * fallback `data.orderNo`); uma recusa síncrona sai pelo mesmo envelope fiat,
 * sempre HTTP 200 (ADR-0001 — a família Fiat nunca responde HTTP de erro para
 * recusa de negócio).
 */
final readonly class RequestFiatWithdrawalController
{
    public function __construct(
        private RequestFiatWithdrawal $requestWithdrawal,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(RequestFiatWithdrawalRequest $request): JsonResponse
    {
        $data = new FiatWithdrawalData(
            currency: $request->string('currency')->toString(),
            paymentMethod: $request->string('apiPaymentMethod')->toString(),
            amount: $request->string('amount')->toString(),
            accountNumber: $request->string('accountInfo.accountNumber')->toString(),
            clientOrderId: $request->string('clientOrderId')->toString(),
            agency: $this->optionalString($request->input('accountInfo.agency')),
            bankCodeForPix: $this->optionalString($request->input('accountInfo.bankCodeForPix')),
            accountType: $this->optionalString($request->input('accountInfo.accountType')),
        );

        try {
            $withdrawal = $this->requestWithdrawal->handle($data);
        } catch (FiatWithdrawRefusedException $fiatWithdrawRefusedException) {
            return $this->errors->make(ErrorFamily::Fiat, $fiatWithdrawRefusedException->errorCode, $fiatWithdrawRefusedException->getMessage());
        }

        return response()->json([
            'code' => '000000',
            'message' => 'success',
            'data' => ['orderId' => $withdrawal->order_id],
        ]);
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
