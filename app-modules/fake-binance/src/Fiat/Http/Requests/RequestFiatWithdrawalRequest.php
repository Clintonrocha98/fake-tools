<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Http\Requests;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Http\Errors\ErrorFamily;
use He4rt\FakeBinance\Http\Errors\ErrorResponseFactory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * O body JSON de POST /sapi/v2/fiat/withdraw — fora da assinatura (query only,
 * `fake-binance.signed`), exatamente como o `RequestFiatWithdrawalRequest` do
 * monolito manda: `currency`, `apiPaymentMethod`, `amount`, `accountInfo` e
 * `clientOrderId`.
 */
final class RequestFiatWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'currency' => ['required', 'string'],
            'apiPaymentMethod' => ['required', 'string'],
            'amount' => ['required', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'accountInfo' => ['required', 'array'],
            'accountInfo.accountNumber' => ['required', 'string'],
            'accountInfo.agency' => ['sometimes', 'nullable', 'string'],
            'accountInfo.bankCodeForPix' => ['sometimes', 'nullable', 'string'],
            'accountInfo.accountType' => ['sometimes', 'nullable', 'string'],
            'clientOrderId' => ['required', 'string'],
        ];
    }

    /**
     * Um parâmetro obrigatório ausente/malformado é -1102 no envelope fiat — a
     * Binance real nunca responde o `{message, errors}` do 422 padrão do Laravel.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            resolve(ErrorResponseFactory::class)->make(ErrorFamily::Fiat, BinanceErrorCode::MandatoryParameterMissing),
        );
    }
}
