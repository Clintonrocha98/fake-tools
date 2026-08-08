<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Um delta de saldo. Viaja como STRING decimal: as contas do ledger são todas
 * `bc*`, e passar por float aqui perderia casas antes de a Action ver o número.
 */
final class LedgerAmountRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric'],
        ];
    }

    /**
     * @return numeric-string
     */
    public function amount(): string
    {
        /** @var numeric-string $amount */
        $amount = (string) $this->string('amount');

        return $amount;
    }
}
