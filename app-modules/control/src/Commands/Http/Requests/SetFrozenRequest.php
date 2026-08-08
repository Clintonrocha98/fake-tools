<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Congelar/descongelar. O valor é explícito e nunca um toggle implícito: o
 * consumidor é uma máquina, e um toggle sob retry deixaria o estado dependendo
 * de quantas vezes a chamada saiu.
 */
final class SetFrozenRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'frozen' => ['required', 'boolean'],
        ];
    }

    public function frozen(): bool
    {
        return $this->boolean('frozen');
    }
}
