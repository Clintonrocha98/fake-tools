<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Quantas releituras o brcode fica invisível. `null` restaura o default (brcode
 * imediato) — por isso `reads` é nullable e não tem `required`.
 */
final class DelayFiatBrcodeRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'reads' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function reads(): ?int
    {
        $reads = $this->input('reads');

        return is_numeric($reads) ? (int) $reads : null;
    }
}
