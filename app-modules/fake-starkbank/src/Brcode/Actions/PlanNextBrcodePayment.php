<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Actions;

use He4rt\FakeStarkbank\Brcode\DTOs\BrcodePaymentPlan;
use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Scenarios\Actions\ConsumeArmedScenario;
use He4rt\FakeStarkbank\Scenarios\Enums\BrcodePaymentOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;

/**
 * O único ponto da perna de BR Code que fala com o cenário armado: consome o
 * que estiver armado e traduz o desfecho num {@see BrcodePaymentPlan}. Sem
 * cenário, devolve o plano neutro.
 *
 * Perna assíncrona: o consumo acontece UMA vez, no `POST /v2/brcode-payment`, e
 * só DEPOIS dos guards do funding — um pagamento recusado por taxId ou valor
 * divergente não gasta o cenário armado, porque ele nunca virou pagamento.
 */
final readonly class PlanNextBrcodePayment
{
    public function __construct(
        private ConsumeArmedScenario $consume = new ConsumeArmedScenario,
    ) {}

    public function handle(): BrcodePaymentPlan
    {
        $armed = $this->consume->handle(PixLeg::StarkbankBrcodePayment);

        if (!$armed instanceof ArmedScenario) {
            return BrcodePaymentPlan::neutral();
        }

        $outcome = $armed->resolvedOutcome();

        if (!$outcome instanceof BrcodePaymentOutcome) {
            return BrcodePaymentPlan::neutral();
        }

        return match ($outcome) {
            BrcodePaymentOutcome::Fail => new BrcodePaymentPlan(
                destinedStatus: BrcodePaymentStatus::Failed,
                failureReason: $armed->payload->reasonOr(BrcodePaymentPlan::DEFAULT_FAILURE_REASON),
            ),
            BrcodePaymentOutcome::Hold => new BrcodePaymentPlan(held: true),
        };
    }
}
