<?php

declare(strict_types=1);

namespace He4rt\Control\State\DTOs;

/**
 * Um cenário armado numa perna. `outcomeLabel` é `null` quando a coluna guarda
 * um desfecho que a perna não conhece mais — o mesmo caso que
 * `PixLeg::outcomeFrom()` / `VenueLeg::outcomeFrom()` já tratam devolvendo
 * `null` em vez de estourar.
 */
final readonly class ArmedScenarioView
{
    /**
     * @param  array<string, int|string>  $payload
     */
    public function __construct(
        public string $leg,
        public string $legLabel,
        public string $outcome,
        public ?string $outcomeLabel,
        public array $payload,
        public string $armedAt,
    ) {}

    /**
     * @return array{leg: string, legLabel: string, outcome: string, outcomeLabel: string|null, payload: array<string, int|string>, armedAt: string}
     */
    public function toArray(): array
    {
        return [
            'leg' => $this->leg,
            'legLabel' => $this->legLabel,
            'outcome' => $this->outcome,
            'outcomeLabel' => $this->outcomeLabel,
            'payload' => $this->payload,
            'armedAt' => $this->armedAt,
        ];
    }
}
