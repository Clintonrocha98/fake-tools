<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Actions;

use He4rt\FakeStarkbank\Invoice\DTOs\InvoicePlan;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Scenarios\Actions\ConsumeArmedScenario;
use He4rt\FakeStarkbank\Scenarios\Enums\InvoiceOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;

/**
 * O único ponto da perna de invoice que fala com o cenário armado: consome o
 * que estiver armado e traduz o desfecho num {@see InvoicePlan}. Sem cenário,
 * devolve o plano neutro.
 *
 * Perna assíncrona: o consumo acontece UMA vez, no `POST /v2/invoice`. As
 * leituras seguintes executam o destino já gravado na linha e nunca voltam à
 * tabela de cenários.
 */
final readonly class PlanNextInvoice
{
    public function __construct(
        private ConsumeArmedScenario $consume = new ConsumeArmedScenario,
    ) {}

    public function handle(): InvoicePlan
    {
        $armed = $this->consume->handle(PixLeg::StarkbankInvoice);

        if (!$armed instanceof ArmedScenario) {
            return InvoicePlan::neutral();
        }

        $outcome = $armed->resolvedOutcome();

        if (!$outcome instanceof InvoiceOutcome) {
            return InvoicePlan::neutral();
        }

        return match ($outcome) {
            InvoiceOutcome::Overdue => new InvoicePlan(destinedStatus: InvoiceStatus::Overdue),
            InvoiceOutcome::Expire => new InvoicePlan(destinedStatus: InvoiceStatus::Expired),
            InvoiceOutcome::Cancel => new InvoicePlan(destinedStatus: InvoiceStatus::Canceled),
            InvoiceOutcome::FreezeStatus => new InvoicePlan(freeze: true),
            InvoiceOutcome::DelayPaid => new InvoicePlan(
                extraSeconds: $armed->payload->extraSecondsOr(InvoicePlan::DEFAULT_EXTRA_SECONDS),
            ),
        };
    }
}
