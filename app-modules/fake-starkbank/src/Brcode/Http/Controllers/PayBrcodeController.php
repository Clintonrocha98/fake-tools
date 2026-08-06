<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Http\Controllers;

use He4rt\FakeStarkbank\Brcode\Actions\PayBrcode;
use He4rt\FakeStarkbank\Brcode\DTOs\BrcodePaymentView;
use He4rt\FakeStarkbank\Brcode\DTOs\PayBrcodeData;
use He4rt\FakeStarkbank\Brcode\Exceptions\BrcodePaymentRefusedException;
use He4rt\FakeStarkbank\Brcode\Exceptions\MalformedBrcodeException;
use He4rt\FakeStarkbank\Brcode\Http\Requests\PayBrcodeRequest;
use He4rt\FakeStarkbank\Http\Errors\ErrorResponseFactory;
use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * `POST /v2/brcode-payment` — a assinatura é verificada pelo middleware
 * `fake-starkbank.signed` na definição da rota, nunca aqui.
 *
 * A resposta mantém a ordem do array recebido: o consumidor lê `payments.0`
 * posicionalmente e trata `payments` vazio ou sem `id` como
 * `StarkbankRequestFailed::malformed()`.
 *
 * Uma recusa aborta o LOTE inteiro — o provedor valida os pagamentos antes de
 * criar qualquer um deles, e responder 200 com metade criada deixaria o
 * consumidor sem como saber qual metade.
 */
final readonly class PayBrcodeController
{
    public function __construct(
        private PayBrcode $payBrcode,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(PayBrcodeRequest $request): JsonResponse
    {
        /** @var array<int, array<array-key, mixed>> $items */
        $items = $request->validated('payments');

        /** @var list<BrcodePaymentView> $payments */
        $payments = [];

        try {
            // A transação é o que torna o lote atômico: sem ela, uma recusa no
            // segundo item deixaria o primeiro pago e o consumidor sem saber
            // qual metade existe.
            DB::transaction(function () use ($items, &$payments): void {
                foreach ($items as $item) {
                    $payments[] = BrcodePaymentView::fromModel(
                        $this->payBrcode->handle(PayBrcodeData::fromWire($item)),
                    );
                }
            });
        } catch (MalformedBrcodeException $malformedBrcodeException) {
            return $this->errors->make(StarkbankErrorCode::InvalidBrcode, $malformedBrcodeException->getMessage());
        } catch (BrcodePaymentRefusedException $brcodePaymentRefusedException) {
            return $this->errors->make($brcodePaymentRefusedException->errorCode, $brcodePaymentRefusedException->getMessage());
        }

        return response()->json(['payments' => $payments]);
    }
}
