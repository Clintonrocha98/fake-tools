<?php

declare(strict_types=1);

namespace He4rt\Venue\Http\Errors;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Os códigos de erro da Binance que a camada de assinatura do fake sabe emitir —
 * o subconjunto coberto por este ticket. Os valores replicam exatamente os códigos
 * negativos documentados pela Binance (ver Error Codes na doc oficial).
 */
enum BinanceErrorCode: int implements HasColor, HasDescription, HasLabel
{
    case InvalidSignature = -1_022;
    case TimestampOutOfWindow = -1_021;
    case ApiKeyMissing = -2_014;
    case ApiKeyInvalid = -2_015;

    public function defaultMessage(): string
    {
        return match ($this) {
            self::InvalidSignature => 'Signature for this request is not valid.',
            self::TimestampOutOfWindow => 'Timestamp for this request is outside of the recvWindow.',
            self::ApiKeyMissing => 'API-key format invalid.',
            self::ApiKeyInvalid => 'Invalid API-key, IP, or permissions for action.',
        };
    }

    public function httpStatus(): int
    {
        return match ($this) {
            self::InvalidSignature, self::TimestampOutOfWindow => 400,
            self::ApiKeyMissing, self::ApiKeyInvalid => 401,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::InvalidSignature => 'Assinatura inválida',
            self::TimestampOutOfWindow => 'Timestamp fora da janela',
            self::ApiKeyMissing => 'API key ausente',
            self::ApiKeyInvalid => 'API key inválida',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InvalidSignature => 'danger',
            self::TimestampOutOfWindow => 'warning',
            self::ApiKeyMissing, self::ApiKeyInvalid => 'danger',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::InvalidSignature => 'HMAC-SHA256 da query não confere com o segredo configurado',
            self::TimestampOutOfWindow => 'Timestamp fora de [serverTime - recvWindow, serverTime + 1000]',
            self::ApiKeyMissing => 'Header X-MBX-APIKEY não enviado',
            self::ApiKeyInvalid => 'Header X-MBX-APIKEY não confere com a chave configurada',
        };
    }
}
