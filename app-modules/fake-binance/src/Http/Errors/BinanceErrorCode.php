<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Http\Errors;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Os códigos de erro da Binance que o fake sabe emitir. As cinco primeiras são
 * o subconjunto de assinatura — negativos, exatamente como a doc oficial de
 * Error Codes. As seis fiat cobrem POST /sapi/v1/fiat/deposit e GET
 * /sapi/v1/fiat/get-order-detail (legacy-docs Fiat Deposit): toda recusa fiat
 * é HTTP 200 (ver {@see self::httpStatus()}), nunca um HTTP de erro — só a
 * assinatura (família spot/wallet OU fiat) usa 400/401. Ver ADR-0001.
 */
enum BinanceErrorCode: int implements HasColor, HasDescription, HasLabel
{
    case InvalidSignature = -1_022;
    case TimestampOutOfWindow = -1_021;
    case TooManyRequests = -1_003;
    case InternalError = -1_001;
    case MandatoryParameterMissing = -1_102;
    case InvalidOrderType = -1_116;
    case InvalidSide = -1_117;
    case InvalidSymbol = -1_121;
    case FilterFailure = -1_013;
    case NewOrderRejected = -2_010;
    case NoSuchOrder = -2_013;
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
            self::TooManyRequests => 'Way too many requests; please try again later.',
            self::InternalError => 'Internal error; unable to process your request. Please try again.',
            self::MandatoryParameterMissing => 'A mandatory parameter was not sent, was empty/null, or malformed.',
            self::InvalidOrderType => 'Invalid orderType.',
            self::InvalidSide => 'Invalid side.',
            self::InvalidSymbol => 'Invalid symbol.',
            self::FilterFailure => 'Filter failure.',
            self::NewOrderRejected => 'Account has insufficient balance for requested action.',
            self::NoSuchOrder => 'Order does not exist.',
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
            self::InternalError => 503,
            self::TooManyRequests => 429,
            self::InvalidSignature, self::TimestampOutOfWindow, self::MandatoryParameterMissing => 400,
            self::InvalidOrderType, self::InvalidSide, self::InvalidSymbol => 400,
            self::FilterFailure => 400,
            self::NewOrderRejected, self::NoSuchOrder => 400,
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
            self::TooManyRequests => 'Rate limit excedido',
            self::InternalError => 'Erro interno (outage)',
            self::MandatoryParameterMissing => 'Parâmetro obrigatório ausente',
            self::InvalidOrderType => 'Tipo de ordem inválido',
            self::InvalidSide => 'Side inválido',
            self::InvalidSymbol => 'Símbolo inválido',
            self::FilterFailure => 'Filtro do símbolo violado',
            self::NewOrderRejected => 'Ordem recusada',
            self::NoSuchOrder => 'Ordem inexistente',
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
            self::TooManyRequests => 'warning',
            self::InternalError => 'danger',
            self::MandatoryParameterMissing => 'warning',
            self::InvalidOrderType => 'warning',
            self::InvalidSide => 'warning',
            self::InvalidSymbol => 'warning',
            self::FilterFailure => 'warning',
            self::NewOrderRejected => 'danger',
            self::NoSuchOrder => 'warning',
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
            self::TooManyRequests => 'Modo rate-limit do painel ligado — todo endpoint responde 429',
            self::InternalError => 'Modo outage do painel ligado — todo endpoint responde 503',
            self::MandatoryParameterMissing => '`timestamp` ou `signature` ausente, vazio ou não numérico',
            self::InvalidOrderType => '`type` diferente de MARKET — o único tipo servido',
            self::InvalidSide => '`side` diferente de BUY ou SELL',
            self::InvalidSymbol => '`symbol` ausente ou diferente de um par servido pelo fake',
            self::FilterFailure => '`quantity` abaixo de `LOT_SIZE.minQty` ou `quoteOrderQty` abaixo de `NOTIONAL.minNotional`',
            self::NewOrderRejected => 'Saldo insuficiente (ordem ou withdraw), `newClientOrderId` ou `withdrawOrderId` duplicado',
            self::NoSuchOrder => '`origClientOrderId` não corresponde a nenhuma ordem conhecida',
            self::ApiKeyMissing => 'Header X-MBX-APIKEY não enviado',
            self::ApiKeyInvalid => 'Header X-MBX-APIKEY não confere com a chave configurada',
            self::FiatServiceNotEnabled => 'Endpoint fiat desligado por config (fake-binance-fiat.deposit_enabled=false)',
            self::FiatDepositLimitExceeded => '`amount` acima do teto configurado (fake-binance-fiat.deposit_limit)',
            self::FiatKycRequired => 'Conta exige verificação KYC antes do depósito fiat',
            self::FiatCurrencyOrMethodUnsupported => '`currency`/`apiPaymentMethod` fora do par configurado (fake-binance-fiat.supported_currency/supported_payment_method)',
            self::FiatOrderNotFound => '`orderNo` não corresponde a nenhuma FiatOrder criada',
            self::FiatChannelUnavailable => 'Canal de pagamento fiat sem capacidade no momento',
        };
    }
}
