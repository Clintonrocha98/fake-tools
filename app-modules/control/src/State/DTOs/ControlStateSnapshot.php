<?php

declare(strict_types=1);

namespace He4rt\Control\State\DTOs;

/**
 * O retrato dos dois fakes. Complementa o feed sem se sobrepor: o feed conta o
 * que aconteceu, o retrato diz onde as coisas estão agora.
 */
final readonly class ControlStateSnapshot
{
    public function __construct(
        public FakeStateSnapshot $starkbank,
        public FakeStateSnapshot $binance,
        public string $takenAt,
    ) {}

    /**
     * @return array{starkbank: array<string, mixed>, binance: array<string, mixed>, takenAt: string}
     */
    public function toArray(): array
    {
        return [
            'starkbank' => $this->starkbank->toArray(),
            'binance' => $this->binance->toArray(),
            'takenAt' => $this->takenAt,
        ];
    }
}
