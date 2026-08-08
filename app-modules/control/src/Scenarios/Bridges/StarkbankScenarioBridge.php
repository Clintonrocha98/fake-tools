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
use He4rt\FakeStarkbank\Scenarios\Actions\ArmScenario;
use He4rt\FakeStarkbank\Scenarios\Actions\DisarmScenario;
use He4rt\FakeStarkbank\Scenarios\Actions\GetArmedScenario;
use He4rt\FakeStarkbank\Scenarios\Actions\GetScenarioSwitchboard;
use He4rt\FakeStarkbank\Scenarios\Actions\ToggleScenarioSwitch;
use He4rt\FakeStarkbank\Scenarios\Contracts\PixLegOutcomeContract;
use He4rt\FakeStarkbank\Scenarios\DTOs\PixScenarioPayload;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Enums\PixScenarioSwitch;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;

/**
 * A malha PIX vista pelo plano de controle. Só embrulha as Actions que o painel
 * já invoca — zero lógica nova de cenário: se algo não sai de uma Action
 * existente, falta uma Action no fake, e isso é outro ticket, não um `if` aqui.
 */
final readonly class StarkbankScenarioBridge implements ScenarioBridgeContract
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
        return 'starkbank';
    }

    /**
     * @return LegView[]
     */
    public function legs(): array
    {
        return array_map(fn (PixLeg $leg): LegView => new LegView(
            leg: $leg->value,
            label: $leg->getLabel(),
            description: $leg->getDescription(),
            outcomes: array_map(static fn (PixLegOutcomeContract $outcome): OutcomeView => new OutcomeView(
                outcome: (string) $outcome->value,
                label: $outcome->getLabel(),
                description: $outcome->getDescription(),
                payloadFields: $outcome->payloadFields(),
            ), $leg->outcomes()),
            armed: $this->viewOrNull($this->armed->handle($leg)),
        ), PixLeg::cases());
    }

    public function arm(string $leg, string $outcome, array $payload): ArmedScenarioView
    {
        $pixLeg = $this->leg($leg);
        $desfecho = $pixLeg->outcomeFrom($outcome);

        if (!$desfecho instanceof PixLegOutcomeContract) {
            throw InvalidControlRequestException::outcomeNotInLeg(
                $outcome,
                $leg,
                array_map(static fn (PixLegOutcomeContract $valido): string => (string) $valido->value, $pixLeg->outcomes()),
            );
        }

        // Só os campos que ESTE desfecho usa: o contrato de outcome é o dono da
        // lista, e o resto é ruído que o VO descartaria em silêncio.
        $aceitos = array_intersect_key($payload, array_flip($desfecho->payloadFields()));

        return $this->view($this->arm->handle($desfecho, PixScenarioPayload::fromArray($aceitos)));
    }

    public function disarm(string $leg): void
    {
        $this->disarm->handle($this->leg($leg));
    }

    public function switchboard(): SwitchboardView
    {
        $switchboard = $this->switchboard->handle();

        return new SwitchboardView(
            switches: array_map(static fn (PixScenarioSwitch $switch): SwitchView => new SwitchView(
                switch: $switch->value,
                label: $switch->getLabel(),
                enabled: (bool) $switchboard->{$switch->column()},
            ), PixScenarioSwitch::cases()),
            rateLimitRetryAfterSeconds: $switchboard->rate_limit_retry_after_seconds,
        );
    }

    public function toggle(string $switch, bool $enabled): SwitchboardView
    {
        $pixSwitch = PixScenarioSwitch::tryFrom($switch);

        if (!$pixSwitch instanceof PixScenarioSwitch) {
            throw InvalidControlRequestException::unknownSwitch(
                $switch,
                array_map(static fn (PixScenarioSwitch $valido): string => $valido->value, PixScenarioSwitch::cases()),
            );
        }

        $this->toggle->handle($pixSwitch, $enabled);

        return $this->switchboard();
    }

    private function leg(string $leg): PixLeg
    {
        return PixLeg::tryFrom($leg) ?? throw InvalidControlRequestException::unknownLeg(
            $leg,
            array_map(static fn (PixLeg $valida): string => $valida->value, PixLeg::cases()),
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
