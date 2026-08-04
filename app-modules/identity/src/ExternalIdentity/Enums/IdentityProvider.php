<?php

declare(strict_types=1);

namespace He4rt\Identity\ExternalIdentity\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum IdentityProvider: string implements HasColor, HasDescription, HasLabel
{
    case GitHub = 'github';

    public function getLabel(): string
    {
        return match ($this) {
            self::GitHub => 'GitHub',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::GitHub => 'warning',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::GitHub => 'GitHub OAuth integration',
        };
    }

    public function getType(): IdentityType
    {
        return match ($this) {
            self::GitHub => IdentityType::External,
        };
    }

    public function getSocialiteDriverName(): string
    {
        return match ($this) {
            self::GitHub => 'github',
        };
    }
}
