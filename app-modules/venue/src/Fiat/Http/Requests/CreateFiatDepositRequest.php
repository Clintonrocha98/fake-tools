<?php

declare(strict_types=1);

namespace He4rt\Venue\Fiat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
}
