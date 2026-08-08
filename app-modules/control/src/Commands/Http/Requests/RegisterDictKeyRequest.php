<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Requests;

use He4rt\FakeStarkbank\Dict\Enums\DictKeyType;
use He4rt\FakeStarkbank\Dict\Enums\DictOwnerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * O registro de uma chave DICT. Os campos de banco são opcionais: quem registra
 * uma chave está interessado no titular, e os defaults vêm da config do módulo
 * pelo próprio {@see \He4rt\FakeStarkbank\Dict\DTOs\RegisterDictKeyData}.
 */
final class RegisterDictKeyRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'pixKey' => ['required', 'string'],
            'type' => ['required', Rule::enum(DictKeyType::class)],
            'name' => ['required', 'string'],
            'taxId' => ['required', 'string'],
            'ownerType' => ['required', Rule::enum(DictOwnerType::class)],
            'bankName' => ['sometimes', 'string'],
            'ispb' => ['sometimes', 'string'],
            'accountType' => ['sometimes', 'string'],
        ];
    }

    /**
     * O DTO do fake lê snake_case (é o shape do form do painel); a wire do
     * plano de controle fala camelCase como o resto do `/control`.
     *
     * @return array<string, mixed>
     */
    public function toDictForm(): array
    {
        return array_filter([
            'pix_key' => $this->input('pixKey'),
            'type' => $this->input('type'),
            'name' => $this->input('name'),
            'tax_id' => $this->input('taxId'),
            'owner_type' => $this->input('ownerType'),
            'bank_name' => $this->input('bankName'),
            'ispb' => $this->input('ispb'),
            'account_type' => $this->input('accountType'),
        ], static fn (mixed $valor): bool => $valor !== null);
    }
}
