<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Exceptions;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use RuntimeException;

/**
 * `orderNo` de GET /sapi/v1/fiat/get-order-detail sem FiatOrder correspondente
 * — HTTP 200 com -16011 no envelope fiat, nunca 404 (mesma regra de família de
 * {@see FiatDepositRefusedException}).
 */
final class FiatOrderNotFoundException extends RuntimeException
{
    public readonly BinanceErrorCode $errorCode;

    private function __construct(string $orderNo)
    {
        $this->errorCode = BinanceErrorCode::FiatOrderNotFound;

        parent::__construct(sprintf('%s: %s', BinanceErrorCode::FiatOrderNotFound->defaultMessage(), $orderNo));
    }

    public static function forOrderNo(string $orderNo): self
    {
        return new self($orderNo);
    }
}
