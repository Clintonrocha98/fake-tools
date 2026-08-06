<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Database\Factories\Brcode;

use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Support\NumericId;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrcodePayment>
 */
final class BrcodePaymentFactory extends Factory
{
    protected $model = BrcodePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $correlationId = 'conversion-'.fake()->uuid();

        return [
            'id' => NumericId::generate(),
            // Um copia-e-cola qualquer: quem precisa de um EMV que DECODIFICA
            // monta o seu no teste, porque o registro guarda o código verbatim
            // e nunca o relê.
            'brcode' => '00020126BR.GOV.BCB.PIX-EMV',
            'tax_id' => '20.018.183/0001-80',
            'amount' => fake()->numberBetween(1_000, 500_000),
            'status' => BrcodePaymentStatus::Created,
            'description' => 'BRD funding '.$correlationId,
            'tags' => [$correlationId],
        ];
    }

    public function processing(): self
    {
        return $this->state(fn (): array => ['status' => BrcodePaymentStatus::Processing]);
    }

    public function settled(): self
    {
        return $this->state(fn (): array => ['status' => BrcodePaymentStatus::Success]);
    }

    public function failed(): self
    {
        return $this->state(fn (): array => ['status' => BrcodePaymentStatus::Failed]);
    }
}
