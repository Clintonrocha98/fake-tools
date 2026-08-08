<?php

declare(strict_types=1);

namespace He4rt\Control\Scenarios\Bridges;

use He4rt\Control\Http\Exceptions\InvalidControlRequestException;
use He4rt\Control\Scenarios\Contracts\ScenarioBridgeContract;
use He4rt\Control\Scenarios\DTOs\LegView;
use He4rt\Control\Scenarios\DTOs\OutcomeView;
use He4rt\Control\State\DTOs\ArmedScenarioView;
use He4rt\Control\State\DTOs\SwitchboardView;
use He4rt\Control\State\DTOs\SwitchView;
use He4rt\FakeBinance\Scenarios\Actions\ArmScenario;
use He4rt\FakeBinance\Scenarios\Actions\DisarmScenario;
use He4rt\FakeBinance\Scenarios\Actions\GetArmedScenario;
use He4rt\FakeBinance\Scenarios\Actions\GetScenarioSwitchboard;
use He4rt\FakeBinance\Scenarios\Actions\ToggleScenarioSwitch;
use He4rt\FakeBinance\Scenarios\Contracts\LegOutcomeContract;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\ScenarioSwitch;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;

/**
 * A venue vista pelo plano de controle. Gêmea da bridge da malha PIX em forma,
 * e deliberadamente separada dela em substância: as Actions têm nome idêntico
 * nos dois fakes mas vivem em módulos que não se conhecem, e é o segmento
 * `{fake}` da rota que decide qual conjunto usar.
 */
final readonly class BinanceScenarioBridge implements ScenarioBridgeContract
{
    public function __construct(
        private ArmScenario $arm = new ArmScenario,
        private DisarmScenario $disarm = new DisarmScenario,
        private GetArmedScenario $armed = new GetArmedScenario,
        private GetScenarioSwitchboard $switchboard = new GetScenarioSwitchboard,
        private ToggleScenarioSwitch $toggle = new ToggleScenarioSwitch,
    ) {}

    public function fake(): string
    {
        return 'binance';
    }

    /**
     * @return LegView[]
     */
    public function legs(): array
    {
        return array_map(fn (VenueLeg $leg): LegView => new LegView(
            leg: $leg->value,
            label: $leg->getLabel(),
            description: $leg->getDescription(),
            outcomes: array_map(static fn (LegOutcomeContract $outcome): OutcomeView => new OutcomeView(
                outcome: (string) $outcome->value,
                label: $outcome->getLabel(),
                description: $outcome->getDescription(),
                payloadFields: $outcome->payloadFields(),
            ), $leg->outcomes()),
            armed: $this->viewOrNull($this->armed->handle($leg)),
        ), VenueLeg::cases());
    }

    public function arm(string $leg, string $outcome, array $payload): ArmedScenarioView
    {
        $venueLeg = $this->leg($leg);
        $desfecho = $venueLeg->outcomeFrom($outcome);

        if (!$desfecho instanceof LegOutcomeContract) {
            throw InvalidControlRequestException::outcomeNotInLeg(
                $outcome,
                $leg,
                array_map(static fn (LegOutcomeContract $valido): string => (string) $valido->value, $venueLeg->outcomes()),
            );
        }

        // Só os campos que ESTE desfecho usa: o contrato de outcome é o dono da
        // lista, e o resto é ruído que o VO descartaria em silêncio.
        $aceitos = array_intersect_key($payload, array_flip($desfecho->payloadFields()));

        return $this->view($this->arm->handle($desfecho, ArmedScenarioPayload::fromArray($aceitos)));
    }

    public function disarm(string $leg): void
    {
        $this->disarm->handle($this->leg($leg));
    }

    public function switchboard(): SwitchboardView
    {
        $switchboard = $this->switchboard->handle();

        return new SwitchboardView(
            switches: array_map(static fn (ScenarioSwitch $switch): SwitchView => new SwitchView(
                switch: $switch->value,
                label: $switch->getLabel(),
                enabled: (bool) $switchboard->{$switch->column()},
            ), ScenarioSwitch::cases()),
            rateLimitRetryAfterSeconds: $switchboard->rate_limit_retry_after_seconds,
        );
    }

    public function toggle(string $switch, bool $enabled): SwitchboardView
    {
        $venueSwitch = ScenarioSwitch::tryFrom($switch);

        if (!$venueSwitch instanceof ScenarioSwitch) {
            throw InvalidControlRequestException::unknownSwitch(
                $switch,
                array_map(static fn (ScenarioSwitch $valido): string => $valido->value, ScenarioSwitch::cases()),
            );
        }

        $this->toggle->handle($venueSwitch, $enabled);

        return $this->switchboard();
    }

    private function leg(string $leg): VenueLeg
    {
        return VenueLeg::tryFrom($leg) ?? throw InvalidControlRequestException::unknownLeg(
            $leg,
            array_map(static fn (VenueLeg $valida): string => $valida->value, VenueLeg::cases()),
        );
    }

    private function viewOrNull(?ArmedScenario $armado): ?ArmedScenarioView
    {
        return $armado instanceof ArmedScenario ? $this->view($armado) : null;
    }

    private function view(ArmedScenario $armado): ArmedScenarioView
    {
        return new ArmedScenarioView(
            leg: $armado->leg->value,
            legLabel: $armado->leg->getLabel(),
            outcome: $armado->outcome,
            outcomeLabel: $armado->resolvedOutcome()?->getLabel(),
            payload: $armado->payload->toArray(),
            armedAt: $armado->armed_at->toIso8601String(),
        );
    }
}
