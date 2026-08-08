<?php

declare(strict_types=1);

namespace He4rt\Control\State\DTOs;

/**
 * O switchboard global de um fake. Os dois fakes têm switchboards separados de
 * propósito — `outage` ligado num não alcança nenhuma rota do outro —, então
 * este DTO descreve um por vez, nunca os dois juntos.
 */
final readonly class SwitchboardView
{
    /**
     * @param  list<SwitchView>  $switches
     */
    public function __construct(
        public array $switches,
        public int $rateLimitRetryAfterSeconds,
    ) {}

    /**
     * @return array{switches: list<array{switch: string, label: string, enabled: bool}>, rateLimitRetryAfterSeconds: int}
     */
    public function toArray(): array
    {
        return [
            'switches' => array_map(static fn (SwitchView $switch): array => $switch->toArray(), $this->switches),
            'rateLimitRetryAfterSeconds' => $this->rateLimitRetryAfterSeconds,
        ];
    }
}
