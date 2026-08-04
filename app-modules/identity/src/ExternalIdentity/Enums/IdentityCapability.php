<?php

declare(strict_types=1);

namespace He4rt\Identity\ExternalIdentity\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum IdentityCapability: string implements HasColor, HasDescription, HasLabel
{
    case Projects = 'projects';
    case Companies = 'companies';
    case Notes = 'notes';

    public function getLabel(): string
    {
        return match ($this) {
            self::Projects => 'Projects',
            self::Companies => 'Companies',
            self::Notes => 'Notes',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Projects => 'info',
            self::Companies => 'success',
            self::Notes => 'gray',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Projects => 'Projects synced from the external platform',
            self::Companies => 'Companies synced from the external platform',
            self::Notes => 'Notes synced from the external platform',
        };
    }

    /**
     * Owns the metadata key convention for this capability's last-sync timestamp.
     */
    public function lastSyncedAtKey(): string
    {
        return sprintf('last_%s_sync_at', $this->value);
    }
}
