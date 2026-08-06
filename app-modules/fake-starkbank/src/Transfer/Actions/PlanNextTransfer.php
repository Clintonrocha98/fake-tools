<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\Actions;

use He4rt\FakeStarkbank\Scenarios\Actions\ConsumeArmedScenario;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Enums\TransferOutcome;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\FakeStarkbank\Transfer\DTOs\TransferPlan;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;

/**
 * O único ponto da perna de transfer que fala com o cenário armado: consome o
 * que estiver armado e traduz o desfecho num {@see TransferPlan}. Sem cenário,
 * devolve o plano neutro.
 *
 * Perna assíncrona: o consumo acontece UMA vez, no `POST /v2/transfer`. Um POST
 * repetido com o mesmo `externalId` NÃO consome de novo — ele devolve a transfer
 * já existente, e um retry não pode gastar o cenário que o operador armou para
 * o próximo cash-out de verdade.
 */
final readonly class PlanNextTransfer
{
    public function __construct(
        private ConsumeArmedScenario $consume = new ConsumeArmedScenario,
    ) {}

    public function handle(): TransferPlan
    {
        $armed = $this->consume->handle(PixLeg::StarkbankTransfer);

        if (!$armed instanceof ArmedScenario) {
            return TransferPlan::neutral();
        }

        $outcome = $armed->resolvedOutcome();

        if (!$outcome instanceof TransferOutcome) {
            return TransferPlan::neutral();
        }

        return match ($outcome) {
            TransferOutcome::Fail => new TransferPlan(
                destinedStatus: TransferStatus::Failed,
                failureReason: $armed->payload->reasonOr(TransferPlan::DEFAULT_FAILURE_REASON),
            ),
            TransferOutcome::Hold => new TransferPlan(held: true),
        };
    }
}
