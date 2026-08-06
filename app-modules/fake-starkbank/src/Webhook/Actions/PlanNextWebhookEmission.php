<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\Actions;

use He4rt\FakeStarkbank\Scenarios\Actions\ConsumeArmedScenario;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Enums\WebhookOutcome;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\FakeStarkbank\Webhook\DTOs\WebhookEmissionPlan;

/**
 * O único ponto da perna de webhook que fala com o cenário armado: consome o
 * que estiver armado e traduz o desfecho num {@see WebhookEmissionPlan}. Sem
 * cenário, devolve o plano neutro.
 *
 * Perna POR EVENTO: o consumo acontece a cada emissão, seja de qual perna for a
 * entity — "armar" aqui vale para o próximo evento, não para a próxima invoice.
 */
final readonly class PlanNextWebhookEmission
{
    public function __construct(
        private ConsumeArmedScenario $consume = new ConsumeArmedScenario,
    ) {}

    public function handle(): WebhookEmissionPlan
    {
        $armed = $this->consume->handle(PixLeg::StarkbankWebhook);

        if (!$armed instanceof ArmedScenario) {
            return WebhookEmissionPlan::neutral();
        }

        $outcome = $armed->resolvedOutcome();

        if (!$outcome instanceof WebhookOutcome) {
            return WebhookEmissionPlan::neutral();
        }

        return match ($outcome) {
            WebhookOutcome::DuplicateNext => new WebhookEmissionPlan(duplicate: true),
            WebhookOutcome::CorruptSignatureNext => new WebhookEmissionPlan(corruptSignature: true),
            WebhookOutcome::HoldNext => new WebhookEmissionPlan(held: true),
        };
    }
}
