<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Database\Factories\Transfer;

use He4rt\FakeStarkbank\Dict\Support\OpaqueAccountBlob;
use He4rt\FakeStarkbank\Support\NumericId;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
final class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pixKey = fake()->safeEmail();
        $correlationId = 'payout-'.fake()->uuid();

        return [
            'id' => NumericId::generate(),
            'amount' => fake()->numberBetween(1_000, 500_000),
            'name' => fake()->name(),
            'tax_id' => '012.345.678-90',
            'bank_code' => '20018183',
            'branch_code' => OpaqueAccountBlob::forBranch($pixKey),
            'account_number' => OpaqueAccountBlob::forAccount($pixKey),
            'account_type' => 'checking',
            'external_id' => $correlationId,
            'status' => TransferStatus::Created,
            'tags' => [$correlationId],
        ];
    }

    public function processing(): self
    {
        return $this->state(fn (): array => ['status' => TransferStatus::Processing]);
    }

    public function settled(): self
    {
        return $this->state(fn (): array => ['status' => TransferStatus::Success]);
    }

    public function failed(): self
    {
        return $this->state(fn (): array => ['status' => TransferStatus::Failed]);
    }
}
