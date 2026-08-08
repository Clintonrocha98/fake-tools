<?php

declare(strict_types=1);

namespace He4rt\Control\Scenarios\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * O body de `POST /control/{fake}/scenarios`.
 *
 * A lista de campos válidos do `payload` NÃO vive aqui: quem a conhece é o
 * `payloadFields()` do desfecho escolhido, e a bridge filtra por ele. Duplicar
 * a lista neste FormRequest a faria divergir no primeiro desfecho novo.
 */
final class ArmScenarioRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'leg' => ['required', 'string'],
            'outcome' => ['required', 'string'],
            'payload' => ['sometimes', 'array'],
        ];
    }

    public function leg(): string
    {
        return (string) $this->string('leg');
    }

    public function outcome(): string
    {
        return (string) $this->string('outcome');
    }

    /**
     * @return array<string, mixed>
     */
    public function scenarioPayload(): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->array('payload');

        return $payload;
    }
}
