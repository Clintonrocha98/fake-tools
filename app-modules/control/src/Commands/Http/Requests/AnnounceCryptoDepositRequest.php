<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A única perna cujo dinheiro não nasce de um pedido do consumidor. `txId`
 * omitido é sintetizado pela Action.
 */
final class AnnounceCryptoDepositRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'coin' => ['required', 'string'],
            'network' => ['required', 'string'],
            'amount' => ['required', 'numeric'],
            'txId' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function coin(): string
    {
        return (string) $this->string('coin');
    }

    public function network(): string
    {
        return (string) $this->string('network');
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

    public function txId(): ?string
    {
        $txId = $this->input('txId');

        return is_string($txId) && $txId !== '' ? $txId : null;
    }
}
