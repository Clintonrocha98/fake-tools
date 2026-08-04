<?php

declare(strict_types=1);

namespace He4rt\Identity\ExternalIdentity\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum CredentialsType: string implements HasColor, HasDescription, HasLabel
{
    case OAuth2 = 'oauth2';
    case ApiKey = 'api_key';
    case Basic = 'basic';

    public function getLabel(): string
    {
        return match ($this) {
            self::OAuth2 => 'OAuth 2.0',
            self::ApiKey => 'API Key',
            self::Basic => 'Basic Auth',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OAuth2 => 'success',
            self::ApiKey => 'info',
            self::Basic => 'warning',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::OAuth2 => 'Token-based OAuth 2.0 authorization flow',
            self::ApiKey => 'Static API key credential',
            self::Basic => 'Username and password authentication',
        };
    }
}
