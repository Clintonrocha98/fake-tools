<?php

declare(strict_types=1);

use He4rt\Identity\ExternalIdentity\Enums\IdentityCapability;

describe('IdentityCapability', static function (): void {
    test('exposes the metadata key for its last sync timestamp', function (): void {
        expect(IdentityCapability::Projects->lastSyncedAtKey())->toBe('last_projects_sync_at')
            ->and(IdentityCapability::Companies->lastSyncedAtKey())->toBe('last_companies_sync_at')
            ->and(IdentityCapability::Notes->lastSyncedAtKey())->toBe('last_notes_sync_at');
    });
});
