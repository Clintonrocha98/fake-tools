<?php

declare(strict_types=1);

namespace He4rt\Venue\Ledger\DTOs;

use JsonSerializable;

final readonly class AccountBalance implements JsonSerializable
{
    public function __construct(
        public string $asset,
        public string $free,
        public string $locked,
    ) {}

    /**
     * @return array{asset: string, free: string, locked: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'asset' => $this->asset,
            'free' => $this->free,
            'locked' => $this->locked,
        ];
    }
}
