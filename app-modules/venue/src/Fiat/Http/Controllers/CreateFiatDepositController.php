<?php

declare(strict_types=1);

namespace He4rt\Venue\Fiat\Http\Controllers;

use He4rt\Venue\Fiat\Actions\OpenFiatDeposit;
use He4rt\Venue\Fiat\Exceptions\FiatDepositRefusedException;
use He4rt\Venue\Fiat\Http\Requests\CreateFiatDepositRequest;
use He4rt\Venue\Http\Errors\ErrorFamily;
use He4rt\Venue\Http\Errors\VenueErrorResponseFactory;
use Illuminate\Http\JsonResponse;

/**
 * POST /sapi/v1/fiat/deposit — sucesso é `{code: "000000", message, data:
 * {orderId}}`; uma recusa síncrona sai pelo mesmo envelope fiat, sempre HTTP
 * 200 (a família Fiat nunca responde HTTP de erro para uma recusa de negócio).
 */
final readonly class CreateFiatDepositController
{
    public function __construct(
        private OpenFiatDeposit $openFiatDeposit,
        private VenueErrorResponseFactory $errors,
    ) {}

    public function __invoke(CreateFiatDepositRequest $request): JsonResponse
    {
        try {
            $order = $this->openFiatDeposit->handle(
                currency: $request->string('currency')->toString(),
                paymentMethod: $request->string('apiPaymentMethod')->toString(),
                amount: $request->string('amount')->toString(),
            );
        } catch (FiatDepositRefusedException $fiatDepositRefusedException) {
            return $this->errors->make(ErrorFamily::Fiat, $fiatDepositRefusedException->errorCode, $fiatDepositRefusedException->getMessage());
        }

        return response()->json([
            'code' => '000000',
            'message' => 'success',
            'data' => ['orderId' => $order->order_no],
        ]);
    }
}
