<?php

declare(strict_types=1);

namespace He4rt\Venue\Http\Errors;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Os códigos de erro da Binance que o fake sabe emitir. As cinco primeiras são
 * o subconjunto de assinatura coberto por story/2 — negativos, exatamente como
 * a doc oficial de Error Codes. As seis fiat (story/4) cobrem POST
 * /sapi/v1/fiat/deposit e GET /sapi/v1/fiat/get-order-detail (legacy-docs Fiat
 * Deposit): toda recusa fiat é HTTP 200 (ver {@see self::httpStatus()}), nunca
 * um HTTP de erro — só a assinatura (família spot/wallet OU fiat) usa 400/401.
 */
enum BinanceErrorCode: int implements HasColor, HasDescription, HasLabel
{
    case InvalidSignature = -1_022;
    case TimestampOutOfWindow = -1_021;
    case MandatoryParameterMissing = -1_102;
    case ApiKeyMissing = -2_014;
    case ApiKeyInvalid = -2_015;

    case FiatServiceNotEnabled = 100_001;
    case FiatDepositLimitExceeded = -16_007;
    case FiatKycRequired = -16_009;
    case FiatCurrencyOrMethodUnsupported = -16_010;
    case FiatOrderNotFound = -16_011;
    case FiatChannelUnavailable = -16_012;

    public function defaultMessage(): string
    {
        return match ($this) {
            self::InvalidSignature => 'Signature for this request is not valid.',
            self::TimestampOutOfWindow => 'Timestamp for this request is outside of the recvWindow.',
            self::MandatoryParameterMissing => 'A mandatory parameter was not sent, was empty/null, or malformed.',
            self::ApiKeyMissing => 'API-key format invalid.',
            self::ApiKeyInvalid => 'Invalid API-key, IP, or permissions for action.',
            self::FiatServiceNotEnabled => 'fiat service not enabled',
            self::FiatDepositLimitExceeded => 'fiat deposit limit exceeded',
            self::FiatKycRequired => 'KYC verification required',
            self::FiatCurrencyOrMethodUnsupported => 'unsupported fiat currency or payment method',
            self::FiatOrderNotFound => 'fiat order not found',
            self::FiatChannelUnavailable => 'no payment channel available',
        };
    }

    public function httpStatus(): int
    {
        return match ($this) {
            self::InvalidSignature, self::TimestampOutOfWindow, self::MandatoryParameterMissing => 400,
            self::ApiKeyMissing, self::ApiKeyInvalid => 401,
            self::FiatServiceNotEnabled, self::FiatDepositLimitExceeded, self::FiatKycRequired,
            self::FiatCurrencyOrMethodUnsupported, self::FiatOrderNotFound, self::FiatChannelUnavailable => 200,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::InvalidSignature => 'Assinatura inválida',
            self::TimestampOutOfWindow => 'Timestamp fora da janela',
            self::MandatoryParameterMissing => 'Parâmetro obrigatório ausente',
            self::ApiKeyMissing => 'API key ausente',
            self::ApiKeyInvalid => 'API key inválida',
            self::FiatServiceNotEnabled => 'Serviço fiat desabilitado',
            self::FiatDepositLimitExceeded => 'Limite de depósito excedido',
            self::FiatKycRequired => 'KYC pendente',
            self::FiatCurrencyOrMethodUnsupported => 'Moeda ou método não suportado',
            self::FiatOrderNotFound => 'Ordem fiat inexistente',
            self::FiatChannelUnavailable => 'Canal de pagamento indisponível',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InvalidSignature => 'danger',
            self::TimestampOutOfWindow => 'warning',
            self::MandatoryParameterMissing => 'warning',
            self::ApiKeyMissing, self::ApiKeyInvalid => 'danger',
            self::FiatServiceNotEnabled => 'danger',
            self::FiatDepositLimitExceeded => 'warning',
            self::FiatKycRequired => 'warning',
            self::FiatCurrencyOrMethodUnsupported => 'danger',
            self::FiatOrderNotFound => 'danger',
            self::FiatChannelUnavailable => 'warning',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::InvalidSignature => 'HMAC-SHA256 da query não confere com o segredo configurado',
            self::TimestampOutOfWindow => 'Timestamp fora de [serverTime - recvWindow, serverTime + 1000]',
            self::MandatoryParameterMissing => '`timestamp` ou `signature` ausente, vazio ou não numérico',
            self::ApiKeyMissing => 'Header X-MBX-APIKEY não enviado',
            self::ApiKeyInvalid => 'Header X-MBX-APIKEY não confere com a chave configurada',
            self::FiatServiceNotEnabled => 'Endpoint fiat desligado por config (venue-fiat.deposit_enabled=false)',
            self::FiatDepositLimitExceeded => '`amount` acima do teto configurado (venue-fiat.deposit_limit)',
            self::FiatKycRequired => 'Conta exige verificação KYC antes do depósito fiat',
            self::FiatCurrencyOrMethodUnsupported => '`currency`/`apiPaymentMethod` fora do par configurado (venue-fiat.supported_currency/supported_payment_method)',
            self::FiatOrderNotFound => '`orderNo` não corresponde a nenhuma FiatOrder criada',
            self::FiatChannelUnavailable => 'Canal de pagamento fiat sem capacidade no momento',
        };
    }
}
