<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Exceptions;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use RuntimeException;

/**
 * Recusa síncrona de POST /sapi/v2/fiat/withdraw — carrega o código fiat que o
 * controller serializa no envelope HTTP 200 (ADR-0001), como
 * {@see FiatDepositRefusedException} faz na entrada.
 */
final class FiatWithdrawRefusedException extends RuntimeException
{
    private function __construct(
        public readonly BinanceErrorCode $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function unsupportedCurrencyOrMethod(string $currency, string $paymentMethod): self
    {
        return new self(
            BinanceErrorCode::FiatCurrencyOrMethodUnsupported,
            sprintf('unsupported fiat currency or payment method: %s via %s', $currency, $paymentMethod),
        );
    }

    public static function insufficientBalance(string $currency, string $amount): self
    {
        return new self(
            BinanceErrorCode::FiatInsufficientBalance,
            sprintf('insufficient %s balance for a %s withdraw', $currency, $amount),
        );
    }
}
