<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\Http\Requests;

use He4rt\FakeStarkbank\Http\Errors\ErrorResponseFactory;
use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

/**
 * O body de `POST /v2/transfer`: o envelope plural `{"transfers": [...]}`, no
 * camelCase da wire. O consumidor manda sempre um item; o fake aceita N, como o
 * StarkBank real.
 *
 * `branchCode` e `accountNumber` são validados só como string presente: são os
 * blobs opacos que o DICT emitiu, e conferir o formato aqui recusaria um
 * beneficiário legítimo cuja entry não veio do seed deste fake.
 */
final class SendTransferRequest extends FormRequest
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
            'transfers' => ['required', 'array', 'min:1'],
            'transfers.*.amount' => ['required', 'integer', 'min:1'],
            'transfers.*.name' => ['required', 'string', 'max:255'],
            'transfers.*.taxId' => ['required', 'string', 'max:255'],
            // O ISPB de 8 dígitos é o que seleciona o trilho PIX; um código
            // COMPE de 3 dígitos rotearia para TED no provedor real.
            'transfers.*.bankCode' => ['required', 'string', 'max:255'],
            'transfers.*.branchCode' => ['required', 'string'],
            'transfers.*.accountNumber' => ['required', 'string'],
            'transfers.*.accountType' => ['sometimes', 'nullable', 'string', 'in:checking,savings'],
            'transfers.*.externalId' => ['sometimes', 'nullable', 'string', 'max:255'],
            'transfers.*.tags' => ['sometimes', 'array'],
            'transfers.*.tags.*' => ['string'],
        ];
    }

    /**
     * Uma recusa de validação sai no envelope de erro do StarkBank, nunca no
     * `{message, errors}` do 422 padrão do Laravel: o consumidor lê
     * `errors.0.code`, e sem isso ele reporta ao operador só "HTTP 422".
     */
    protected function failedValidation(Validator $validator): never
    {
        $motivo = (string) collect($validator->errors()->all())->first();

        Log::warning('fake-starkbank.transfer: cash-out recusado na validação — o StarkBank real também recusa antes de mover dinheiro, e aceitar aqui esconderia do consumidor um payload que o provedor rejeitaria', [
            'motivo' => $motivo,
            'campos' => array_keys($validator->errors()->messages()),
        ]);

        throw new HttpResponseException(
            resolve(ErrorResponseFactory::class)->make(StarkbankErrorCode::InvalidRequest, $motivo),
        );
    }
}
