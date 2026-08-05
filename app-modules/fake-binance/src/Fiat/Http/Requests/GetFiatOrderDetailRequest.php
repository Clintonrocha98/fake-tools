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
 * A query de GET /sapi/v1/fiat/get-order-detail?orderNo=… — fora da assinatura
 * (query only, `fake-binance.signed`), exatamente como o `GetFiatOrderDetailRequest`
 * do monolito manda.
 */
final class GetFiatOrderDetailRequest extends FormRequest
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
            'orderNo' => ['required', 'string'],
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
