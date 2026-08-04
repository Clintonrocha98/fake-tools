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
    case MandatoryParameterMissing = -1_102;
    case NewOrderRejected = -2_010;
    case NoSuchOrder = -2_013;
    case ApiKeyMissing = -2_014;
    case ApiKeyInvalid = -2_015;

    public function defaultMessage(): string
    {
        return match ($this) {
            self::InvalidSignature => 'Signature for this request is not valid.',
            self::TimestampOutOfWindow => 'Timestamp for this request is outside of the recvWindow.',
            self::MandatoryParameterMissing => 'A mandatory parameter was not sent, was empty/null, or malformed.',
            self::NewOrderRejected => 'NEW_ORDER_REJECTED',
            self::NoSuchOrder => 'Order does not exist.',
            self::ApiKeyMissing => 'API-key format invalid.',
            self::ApiKeyInvalid => 'Invalid API-key, IP, or permissions for action.',
        };
    }

    public function httpStatus(): int
    {
        return match ($this) {
            self::InvalidSignature, self::TimestampOutOfWindow, self::MandatoryParameterMissing => 400,
            self::NewOrderRejected, self::NoSuchOrder => 400,
            self::ApiKeyMissing, self::ApiKeyInvalid => 401,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::InvalidSignature => 'Assinatura inválida',
            self::TimestampOutOfWindow => 'Timestamp fora da janela',
            self::MandatoryParameterMissing => 'Parâmetro obrigatório ausente',
            self::NewOrderRejected => 'Ordem recusada',
            self::NoSuchOrder => 'Ordem inexistente',
            self::ApiKeyMissing => 'API key ausente',
            self::ApiKeyInvalid => 'API key inválida',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InvalidSignature => 'danger',
            self::TimestampOutOfWindow => 'warning',
            self::MandatoryParameterMissing => 'warning',
            self::NewOrderRejected => 'danger',
            self::NoSuchOrder => 'warning',
            self::ApiKeyMissing, self::ApiKeyInvalid => 'danger',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::InvalidSignature => 'HMAC-SHA256 da query não confere com o segredo configurado',
            self::TimestampOutOfWindow => 'Timestamp fora de [serverTime - recvWindow, serverTime + 1000]',
            self::MandatoryParameterMissing => '`timestamp` ou `signature` ausente, vazio ou não numérico',
            self::NewOrderRejected => 'Saldo insuficiente ou `newClientOrderId` duplicado',
            self::NoSuchOrder => '`origClientOrderId` não corresponde a nenhuma ordem conhecida',
            self::ApiKeyMissing => 'Header X-MBX-APIKEY não enviado',
            self::ApiKeyInvalid => 'Header X-MBX-APIKEY não confere com a chave configurada',
        };
    }
}
