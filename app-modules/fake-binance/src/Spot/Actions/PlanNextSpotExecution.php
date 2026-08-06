<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Actions;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Scenarios\Actions\ConsumeArmedScenario;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use He4rt\FakeBinance\Spot\DTOs\SpotExecutionPlan;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;

/**
 * O único ponto da perna spot que fala com o cenário armado: consome o que
 * estiver armado e traduz o desfecho num {@see SpotExecutionPlan}. Sem cenário,
 * devolve o plano neutro.
 */
final readonly class PlanNextSpotExecution
{
    public function __construct(
        private ConsumeArmedScenario $consume = new ConsumeArmedScenario,
    ) {}

    public function handle(): SpotExecutionPlan
    {
        $armed = $this->consume->handle(VenueLeg::SpotConversion);

        if (!$armed instanceof ArmedScenario) {
            return SpotExecutionPlan::neutral();
        }

        $outcome = $armed->resolvedOutcome();

        if (!$outcome instanceof SpotConversionOutcome) {
            return SpotExecutionPlan::neutral();
        }

        return match ($outcome) {
            SpotConversionOutcome::FillPartialExpired => new SpotExecutionPlan(
                fillFraction: $armed->payload->fractionOr(SpotExecutionPlan::DEFAULT_PARTIAL_FRACTION),
                finalStatus: OrderStatus::Expired,
                refusal: null,
                rawStatusOverride: null,
            ),
            SpotConversionOutcome::EmitUnknownStatus => new SpotExecutionPlan(
                fillFraction: '1',
                finalStatus: OrderStatus::Filled,
                refusal: null,
                rawStatusOverride: $armed->payload->rawStatusOr(SpotExecutionPlan::DEFAULT_UNKNOWN_RAW_STATUS),
            ),
            SpotConversionOutcome::RespondRejected => new SpotExecutionPlan(
                fillFraction: '0',
                finalStatus: OrderStatus::Rejected,
                refusal: null,
                rawStatusOverride: null,
            ),
            SpotConversionOutcome::RefuseWithCode => new SpotExecutionPlan(
                fillFraction: '0',
                finalStatus: OrderStatus::Rejected,
                refusal: $this->refusalCode($armed->payload),
                rawStatusOverride: null,
            ),
        };
    }

    /**
     * Só um código que a perna declara ({@see VenueLeg::refusalCodes()}) pode
     * sair daqui. Um código de outra família armado na coluna viajaria dentro
     * do envelope errado — um `-16009` fiat, que responde HTTP 200, no meio de
     * `/api/v3`.
     */
    private function refusalCode(ArmedScenarioPayload $payload): BinanceErrorCode
    {
        $code = $payload->binanceErrorCode();

        return in_array($code, VenueLeg::SpotConversion->refusalCodes(), strict: true)
            ? $code
            : BinanceErrorCode::NewOrderRejected;
    }
}
