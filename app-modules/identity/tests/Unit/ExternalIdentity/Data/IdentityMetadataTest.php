<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\Identity\ExternalIdentity\Data\IdentityMetadata;
use He4rt\Identity\ExternalIdentity\Enums\IdentityCapability;

describe('IdentityMetadata value object', static function (): void {
    test('hydrates typed sync timestamps from a raw payload', function (): void {
        $metadata = IdentityMetadata::fromArray([
            'last_projects_sync_at' => '2026-03-17T15:54:48.000000Z',
        ]);

        expect($metadata->lastSyncAt(IdentityCapability::Projects))
            ->toBeInstanceOf(CarbonImmutable::class)
            ->and($metadata->lastSyncAt(IdentityCapability::Projects)->toISOString())
            ->toBe('2026-03-17T15:54:48.000000Z');
    });

    test('returns null for a capability that was never synced', function (): void {
        expect(IdentityMetadata::empty()->lastSyncAt(IdentityCapability::Notes))->toBeNull();

        expect(IdentityMetadata::fromArray([])->lastSyncAt(IdentityCapability::Projects))->toBeNull();
    });

    test('ignores malformed keys but keeps valid sync entries', function (): void {
        $metadata = IdentityMetadata::fromArray([
            'last_projects_sync_at' => '2026-03-17T15:54:48.000000Z',
            'unrelated_key' => 'whatever',
            'last_projects_sync_at_typo' => 'not a timestamp',
        ]);

        expect($metadata->syncTimestamps)->toHaveCount(1)
            ->and($metadata->lastSyncAt(IdentityCapability::Projects))->not->toBeNull();
    });

    test('rejects a malformed timestamp on a valid key (fail fast)', function (): void {
        expect(fn () => IdentityMetadata::fromArray([
            'last_projects_sync_at' => 'definitely-not-a-date',
        ]))->toThrow(Exception::class);
    });

    test('round-trips through toArray in the last_*_sync_at shape', function (): void {
        $payload = ['last_projects_sync_at' => '2026-03-17T15:54:48.000000Z'];

        expect(IdentityMetadata::fromArray($payload)->toArray())->toBe($payload);
    });

    test('withSyncedNow returns a new immutable instance preserving other capabilities', function (): void {
        CarbonImmutable::setTestNow('2026-07-03T12:00:00.000000Z');

        $original = IdentityMetadata::fromArray([
            'last_companies_sync_at' => '2026-03-17T15:54:48.000000Z',
        ]);

        $updated = $original->withSyncedNow(IdentityCapability::Projects);

        expect($updated)->not->toBe($original)
            ->and($original->lastSyncAt(IdentityCapability::Projects))->toBeNull()
            ->and($updated->lastSyncAt(IdentityCapability::Projects)?->toISOString())->toBe('2026-07-03T12:00:00.000000Z')
            ->and($updated->lastSyncAt(IdentityCapability::Companies)?->toISOString())->toBe('2026-03-17T15:54:48.000000Z');

        CarbonImmutable::setTestNow();
    });
});
