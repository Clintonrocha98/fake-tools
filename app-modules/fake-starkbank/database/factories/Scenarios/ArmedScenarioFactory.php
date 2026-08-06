<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Database\Factories\Scenarios;

use He4rt\FakeStarkbank\Scenarios\DTOs\PixScenarioPayload;
use He4rt\FakeStarkbank\Scenarios\Enums\InvoiceOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Enums\TransferOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\WebhookOutcome;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/** @extends Factory<ArmedScenario> */
class ArmedScenarioFactory extends Factory
{
    protected $model = ArmedScenario::class;

    public function definition(): array
    {
        return [
            'leg' => PixLeg::StarkbankInvoice,
            'outcome' => InvoiceOutcome::Cancel->value,
            'payload' => PixScenarioPayload::empty(),
            'armed_at' => Date::now(),
        ];
    }

    /**
     * Invoice armada para atrasar o pagamento em um minuto além do relógio.
     */
    public function invoiceDelayed(int $extraSeconds = 60): static
    {
        return $this->state(fn (): array => [
            'leg' => PixLeg::StarkbankInvoice,
            'outcome' => InvoiceOutcome::DelayPaid->value,
            'payload' => new PixScenarioPayload(extraSeconds: $extraSeconds),
        ]);
    }

    /**
     * Transfer armada para nascer destinada a `failed`.
     */
    public function transferFailing(string $reason = 'Conta do favorecido encerrada'): static
    {
        return $this->state(fn (): array => [
            'leg' => PixLeg::StarkbankTransfer,
            'outcome' => TransferOutcome::Fail->value,
            'payload' => new PixScenarioPayload(reason: $reason),
        ]);
    }

    /**
     * Webhook armado para represar a próxima emissão.
     */
    public function webhookHeld(): static
    {
        return $this->state(fn (): array => [
            'leg' => PixLeg::StarkbankWebhook,
            'outcome' => WebhookOutcome::HoldNext->value,
            'payload' => PixScenarioPayload::empty(),
        ]);
    }
}
