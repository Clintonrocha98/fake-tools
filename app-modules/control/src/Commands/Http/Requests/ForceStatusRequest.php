<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * O body de todo gesto "forçar status". O enum concreto varia por perna, então
 * a regra vem do controller via {@see self::$enum} — declarar a lista aqui a
 * faria divergir do enum no primeiro case novo.
 */
final class ForceStatusRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required'],
            'info' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function status(): string
    {
        return (string) $this->string('status');
    }

    public function info(): ?string
    {
        $info = $this->input('info');

        return is_string($info) && $info !== '' ? $info : null;
    }
}
