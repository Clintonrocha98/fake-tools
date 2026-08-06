<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Http\Requests;

use He4rt\FakeStarkbank\Http\Errors\ErrorResponseFactory;
use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

/**
 * O body de `POST /v2/invoice`: o envelope plural `{"invoices": [...]}`, no
 * camelCase da wire. O consumidor manda sempre um item; o fake aceita N, como
 * o StarkBank real.
 *
 * `due` e `expiration` são opcionais na doc — omitidos, o default de `due`
 * (agora + 2 dias) é resolvido em {@see \He4rt\FakeStarkbank\Invoice\DTOs\IssueInvoiceData}.
 */
final class IssueInvoiceRequest extends FormRequest
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
            'invoices' => ['required', 'array', 'min:1'],
            'invoices.*.amount' => ['required', 'integer', 'min:1'],
            'invoices.*.name' => ['required', 'string', 'max:255'],
            'invoices.*.taxId' => ['required', 'string', 'max:255'],
            'invoices.*.due' => ['sometimes', 'nullable', 'date'],
            'invoices.*.expiration' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'invoices.*.tags' => ['sometimes', 'array'],
            'invoices.*.tags.*' => ['string'],
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

        Log::warning('fake-starkbank.invoice: emissão recusada na validação — o StarkBank real também recusa antes de gerar brcode, e emitir uma cobrança inconsistente esconderia o defeito do lado do consumidor', [
            'motivo' => $motivo,
            'campos' => array_keys($validator->errors()->messages()),
        ]);

        throw new HttpResponseException(
            resolve(ErrorResponseFactory::class)->make(StarkbankErrorCode::InvalidRequest, $motivo),
        );
    }
}
