<?php

declare(strict_types=1);

namespace He4rt\Identity\ExternalIdentity\Data;

use Carbon\CarbonImmutable;
use He4rt\Identity\ExternalIdentity\Enums\IdentityCapability;

final readonly class IdentityMetadata
{
    /**
     * @param  array<string, CarbonImmutable>  $syncTimestamps  keyed by IdentityCapability value
     */
    private function __construct(
        public array $syncTimestamps = [],
    ) {}

    public static function empty(): self
    {
        return new self();
    }

    /**
     * Hydrate from the raw JSON payload, reading each known capability's
     * timestamp under the key it owns (see IdentityCapability::lastSyncedAtKey()).
     *
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $timestamps = [];

        foreach (IdentityCapability::cases() as $capability) {
            $value = $payload[$capability->lastSyncedAtKey()] ?? null;

            if (is_string($value)) {
                $timestamps[$capability->value] = CarbonImmutable::parse($value);
            }
        }

        return new self($timestamps);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $payload = [];

        foreach (IdentityCapability::cases() as $capability) {
            $timestamp = $this->syncTimestamps[$capability->value] ?? null;

            if ($timestamp instanceof CarbonImmutable) {
                $payload[$capability->lastSyncedAtKey()] = $timestamp->toISOString();
            }
        }

        return $payload;
    }

    public function lastSyncAt(IdentityCapability $capability): ?CarbonImmutable
    {
        return $this->syncTimestamps[$capability->value] ?? null;
    }

    public function withSyncedNow(IdentityCapability $capability): self
    {
        return new self([
            ...$this->syncTimestamps,
            $capability->value => CarbonImmutable::now(),
        ]);
    }
}
