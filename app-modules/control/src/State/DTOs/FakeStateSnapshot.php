<?php

declare(strict_types=1);

namespace He4rt\Control\State\DTOs;

/**
 * Metade do retrato: um fake inteiro, do switchboard aos recursos recentes.
 */
final readonly class FakeStateSnapshot
{
    /**
     * @param  list<ArmedScenarioView>  $armedScenarios
     * @param  list<ResourceGroupView>  $resources
     */
    public function __construct(
        public SwitchboardView $switchboard,
        public array $armedScenarios,
        public array $resources,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $recursos = [];

        foreach ($this->resources as $grupo) {
            $recursos[$grupo->type] = $grupo->toArray();
        }

        return [
            'switchboard' => $this->switchboard->toArray(),
            'armedScenarios' => array_map(
                static fn (ArmedScenarioView $armado): array => $armado->toArray(),
                $this->armedScenarios,
            ),
            ...$recursos,
        ];
    }
}
