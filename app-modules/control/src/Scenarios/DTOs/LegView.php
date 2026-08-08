<?php

declare(strict_types=1);

namespace He4rt\Control\Scenarios\DTOs;

use He4rt\Control\State\DTOs\ArmedScenarioView;

/**
 * Uma perna do fake: os desfechos que ela aceita e o que estiver armado nela.
 * `armed` nulo é "nada armado" — o happy path.
 */
final readonly class LegView
{
    /**
     * @param  list<OutcomeView>  $outcomes
     */
    public function __construct(
        public string $leg,
        public string $label,
        public ?string $description,
        public array $outcomes,
        public ?ArmedScenarioView $armed,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'leg' => $this->leg,
            'label' => $this->label,
            'description' => $this->description,
            'outcomes' => array_map(static fn (OutcomeView $outcome): array => $outcome->toArray(), $this->outcomes),
            'armed' => $this->armed?->toArray(),
        ];
    }
}
