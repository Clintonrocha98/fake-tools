<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * O vocabulário de wire arbitrário que os enums de status não modelam — é o
 * ponto do gesto, então aqui não há lista de valores válidos a impor.
 */
final class EmitUnknownStatusRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'rawStatus' => ['required'],
        ];
    }

    public function rawStatus(): string
    {
        return (string) $this->string('rawStatus');
    }

    public function rawStatusAsInt(): int
    {
        return (int) $this->input('rawStatus');
    }
}
