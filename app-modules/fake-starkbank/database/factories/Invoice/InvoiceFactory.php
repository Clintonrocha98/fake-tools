<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Database\Factories\Invoice;

use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Invoice\Support\SyntheticBrcode;
use He4rt\FakeStarkbank\Support\NumericId;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<Invoice>
 */
final class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = NumericId::generate();
        $amount = fake()->numberBetween(1_000, 500_000);
        $name = fake()->name();

        return [
            'id' => $id,
            'amount' => $amount,
            'name' => $name,
            'tax_id' => '012.345.678-90',
            'status' => InvoiceStatus::Created,
            'brcode' => SyntheticBrcode::forInvoice($id, $amount, $name),
            'tags' => ['deposit-'.fake()->uuid()],
            'due' => Date::now()->addHour(),
            'expiration' => 0,
            'paid_at' => null,
            'expired_at' => null,
            'frozen' => false,
        ];
    }

    public function paid(): self
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => Date::now(),
        ]);
    }

    public function overdue(): self
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Overdue,
            'due' => Date::now()->subMinute(),
            'expiration' => 3_600,
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Expired,
            'due' => Date::now()->subHour(),
            'expiration' => 0,
            'expired_at' => Date::now(),
        ]);
    }

    public function frozen(): self
    {
        return $this->state(fn (): array => ['frozen' => true]);
    }
}
