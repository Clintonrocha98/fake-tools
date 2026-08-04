<?php

declare(strict_types=1);

namespace He4rt\Venue\Fiat\Exceptions;

use He4rt\Venue\Http\Errors\BinanceErrorCode;
use RuntimeException;

/**
 * A recusa síncrona de POST /sapi/v1/fiat/deposit — HTTP 200 com um code de
 * erro fiat no envelope, nunca um HTTP de erro (a família Fiat sempre
 * responde 200; ver {@see BinanceErrorCode::httpStatus()}).
 */
final class FiatDepositRefusedException extends RuntimeException
{
    private function __construct(public readonly BinanceErrorCode $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public static function serviceNotEnabled(): self
    {
        return new self(BinanceErrorCode::FiatServiceNotEnabled, BinanceErrorCode::FiatServiceNotEnabled->defaultMessage());
    }

    public static function unsupportedCurrencyOrMethod(string $currency, string $paymentMethod): self
    {
        return new self(
            BinanceErrorCode::FiatCurrencyOrMethodUnsupported,
            sprintf('%s (%s/%s)', BinanceErrorCode::FiatCurrencyOrMethodUnsupported->defaultMessage(), $currency, $paymentMethod),
        );
    }

    public static function depositLimitExceeded(string $amount, string $limit): self
    {
        return new self(
            BinanceErrorCode::FiatDepositLimitExceeded,
            sprintf('%s (amount %s > limit %s)', BinanceErrorCode::FiatDepositLimitExceeded->defaultMessage(), $amount, $limit),
        );
    }
}
