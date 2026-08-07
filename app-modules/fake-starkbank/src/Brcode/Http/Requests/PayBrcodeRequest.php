<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Http\Requests;

use He4rt\FakeStarkbank\Http\Errors\ErrorResponseFactory;
use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;
use He4rt\FakeStarkbank\Support\StarkbankLog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * O body de `POST /v2/brcode-payment`: o envelope plural `{"payments": [...]}`,
 * no camelCase da wire. O consumidor manda sempre um item; o fake aceita N,
 * como o StarkBank real.
 *
 * `externalId` tem regra PERMISSIVA de propósito. Recusá-lo aqui devolveria a
 * mensagem genérica de validação, e o que o consumidor apanhou ao vivo do
 * provedor é a literal `Unknown parameters in payment: externalId` sob o code
 * `invalidJson` — quem recusa é a Action, e a regra existe só para a chave
 * sobreviver ao `validated()` e chegar até lá.
 */
final class PayBrcodeRequest extends FormRequest
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
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.brcode' => ['required', 'string'],
            'payments.*.taxId' => ['required', 'string', 'max:255'],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            // Obrigatória só no BR Code dinâmico — a Action decide, porque só
            // ela decodifica o código e sabe se o valor está embutido.
            'payments.*.description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payments.*.externalId' => ['sometimes', 'nullable', 'string'],
            'payments.*.tags' => ['sometimes', 'array'],
            'payments.*.tags.*' => ['string'],
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

        StarkbankLog::warning('fake-starkbank.brcode: pagamento recusado na validação — o StarkBank real também recusa antes de mover dinheiro, e aceitar aqui esconderia do consumidor um payload que o provedor rejeitaria', [
            'motivo' => $motivo,
            'campos' => array_keys($validator->errors()->messages()),
        ]);

        throw new HttpResponseException(
            resolve(ErrorResponseFactory::class)->make(StarkbankErrorCode::InvalidRequest, $motivo),
        );
    }
}
