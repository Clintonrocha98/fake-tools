<?php

declare(strict_types=1);

namespace He4rt\Venue\Fiat\Http\Requests;

use He4rt\Venue\Http\Errors\BinanceErrorCode;
use He4rt\Venue\Http\Errors\ErrorFamily;
use He4rt\Venue\Http\Errors\VenueErrorResponseFactory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * O body JSON de POST /sapi/v1/fiat/deposit — fora da assinatura (query only,
 * `venue.signed`), exatamente como o `CreateFiatDepositRequest` do monolito manda.
 */
final class CreateFiatDepositRequest extends FormRequest
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
        ];
    }

    /**
     * Um parâmetro obrigatório ausente/malformado é -1102 no envelope fiat — a
     * Binance real nunca responde o `{message, errors}` do 422 padrão do Laravel.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            resolve(VenueErrorResponseFactory::class)->make(ErrorFamily::Fiat, BinanceErrorCode::MandatoryParameterMissing),
        );
    }
}
