<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Http\Controllers;

use He4rt\FakeStarkbank\Brcode\Actions\GetBrcodePayment;
use He4rt\FakeStarkbank\Brcode\DTOs\BrcodePaymentView;
use He4rt\FakeStarkbank\Brcode\Exceptions\BrcodePaymentNotFoundException;
use He4rt\FakeStarkbank\Http\Errors\ErrorResponseFactory;
use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;
use Illuminate\Http\JsonResponse;

/**
 * `GET /v2/brcode-payment/{id}` — envelope singular `{"payment": {...}}`. A
 * leitura é o que faz o tempo passar: o avanço lazy roda dentro da Action antes
 * de a resposta ser montada.
 */
final readonly class GetBrcodePaymentController
{
    public function __construct(
        private GetBrcodePayment $getBrcodePayment,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        try {
            $payment = $this->getBrcodePayment->handle($id);
        } catch (BrcodePaymentNotFoundException) {
            return $this->errors->make(StarkbankErrorCode::InvalidId);
        }

        return response()->json(['payment' => BrcodePaymentView::fromModel($payment)]);
    }
}
