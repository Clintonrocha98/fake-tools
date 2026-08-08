<?php

declare(strict_types=1);

namespace He4rt\Control\Scenarios\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ToggleScenarioSwitchRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'switch' => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
        ];
    }

    public function switchName(): string
    {
        return (string) $this->string('switch');
    }

    public function enabled(): bool
    {
        return $this->boolean('enabled');
    }
}
