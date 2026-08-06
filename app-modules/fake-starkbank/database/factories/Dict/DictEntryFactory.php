<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Database\Factories\Dict;

use He4rt\FakeStarkbank\Dict\Enums\DictKeyType;
use He4rt\FakeStarkbank\Dict\Enums\DictOwnerType;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use He4rt\FakeStarkbank\Dict\Support\OpaqueAccountBlob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DictEntry>
 */
final class DictEntryFactory extends Factory
{
    protected $model = DictEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pixKey = fake()->unique()->safeEmail();

        return [
            'pix_key' => $pixKey,
            'type' => DictKeyType::Email,
            'name' => fake()->name(),
            'tax_id' => '012.345.678-90',
            'owner_type' => DictOwnerType::NaturalPerson,
            'bank_name' => 'Stark Bank S.A.',
            'ispb' => '20018183',
            'branch_code_blob' => OpaqueAccountBlob::forBranch($pixKey),
            'account_number_blob' => OpaqueAccountBlob::forAccount($pixKey),
            'account_type' => 'checking',
            'status' => 'registered',
        ];
    }

    /**
     * A entry de funding cross-fake: pessoa jurídica com CNPJ, o beneficiário
     * que o BR Code do fake-binance nomeia.
     */
    public function funding(): self
    {
        return $this->state(fn (): array => [
            'name' => 'Fake Binance',
            'tax_id' => '20.018.183/0001-80',
            'owner_type' => DictOwnerType::LegalEntity,
        ]);
    }
}
