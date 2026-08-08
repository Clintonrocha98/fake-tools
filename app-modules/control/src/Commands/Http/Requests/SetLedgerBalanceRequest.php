<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SetLedgerBalanceRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'free' => ['required', 'numeric'],
            'locked' => ['sometimes', 'numeric'],
        ];
    }

    /**
     * @return numeric-string
     */
    public function free(): string
    {
        /** @var numeric-string $free */
        $free = (string) $this->string('free');

        return $free;
    }

    /**
     * @return numeric-string
     */
    public function locked(): string
    {
        /** @var numeric-string $locked */
        $locked = $this->has('locked') ? (string) $this->string('locked') : '0';

        return $locked;
    }
}
