<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Database\Seeders;

use He4rt\FakeStarkbank\Dict\Enums\DictKeyType;
use He4rt\FakeStarkbank\Dict\Enums\DictOwnerType;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use He4rt\FakeStarkbank\Dict\Support\OpaqueAccountBlob;
use He4rt\FakeStarkbank\Support\StarkbankLog;
use Illuminate\Database\Seeder;

/**
 * As duas chaves que o DICT do fake nasce conhecendo: o beneficiário de
 * referência do fixture do consumidor e o recebedor do funding cross-fake.
 *
 * Idempotente por `updateOrCreate` sobre a chave PIX, e não por um guard de
 * "já existe alguma linha": um registro DICT é estado DECLARATIVO — mudar
 * `FAKE_STARKBANK_FUNDING_TAX_ID` e reseedar precisa reescrever a entry, não
 * ser ignorado. É isso que deixa o entrypoint do container rodar o seed em todo
 * start.
 */
final class DictEntrySeeder extends Seeder
{
    public function run(): void
    {
        $this->register(
            pixKey: $this->config('fake-starkbank-dict.reference.pix_key', 'ada@brd.digital'),
            type: DictKeyType::tryFrom($this->config('fake-starkbank-dict.reference.type', 'email')) ?? DictKeyType::Email,
            name: $this->config('fake-starkbank-dict.reference.name', 'Ada Lovelace'),
            taxId: $this->config('fake-starkbank-dict.reference.tax_id', '012.345.678-90'),
            ownerType: DictOwnerType::tryFrom($this->config('fake-starkbank-dict.reference.owner_type', 'naturalPerson')) ?? DictOwnerType::NaturalPerson,
        );

        $this->register(
            pixKey: $this->config('fake-starkbank-dict.funding.pix_key', 'funding@fake-binance.dev'),
            type: DictKeyType::Email,
            name: $this->config('fake-starkbank-dict.funding.name', 'Fake Binance'),
            taxId: $this->config('fake-starkbank-dict.funding.tax_id', '20.018.183/0001-80'),
            ownerType: DictOwnerType::LegalEntity,
        );
    }

    private function register(
        string $pixKey,
        DictKeyType $type,
        string $name,
        string $taxId,
        DictOwnerType $ownerType,
    ): void {
        DictEntry::query()->updateOrCreate(
            ['pix_key' => $pixKey],
            [
                'type' => $type,
                'name' => $name,
                'tax_id' => $taxId,
                'owner_type' => $ownerType,
                'bank_name' => $this->config('fake-starkbank-dict.bank.name', 'Stark Bank S.A.'),
                'ispb' => $this->config('fake-starkbank-dict.bank.ispb', '20018183'),
                'branch_code_blob' => OpaqueAccountBlob::forBranch($pixKey),
                'account_number_blob' => OpaqueAccountBlob::forAccount($pixKey),
                'account_type' => $this->config('fake-starkbank-dict.bank.account_type', 'checking'),
                'status' => $this->config('fake-starkbank-dict.bank.status', 'registered'),
            ],
        );

        StarkbankLog::info('fake-starkbank.dict: chave semeada — sem ela o cash-out não tem beneficiário e o funding cross-fake não tem taxId para o consumidor conferir', [
            'pix_key' => $pixKey,
            'owner_type' => $ownerType->value,
            'tax_id' => $taxId,
        ]);
    }

    private function config(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }
}
