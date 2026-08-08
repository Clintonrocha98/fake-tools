<?php

declare(strict_types=1);

namespace He4rt\Control\Scenarios\DTOs;

/**
 * Um desfecho armável, com os campos de payload que ele de fato usa.
 * `payloadFields` vem do contrato de outcome do próprio fake — é ele que diz o
 * que vale, e duplicar a lista aqui a faria divergir no primeiro desfecho novo.
 */
final readonly class OutcomeView
{
    /**
     * @param  list<string>  $payloadFields
     */
    public function __construct(
        public string $outcome,
        public string $label,
        public ?string $description,
        public array $payloadFields,
    ) {}

    /**
     * @return array{outcome: string, label: string, description: string|null, payloadFields: list<string>}
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome,
            'label' => $this->label,
            'description' => $this->description,
            'payloadFields' => $this->payloadFields,
        ];
    }
}
