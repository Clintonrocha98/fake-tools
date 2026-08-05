<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Http\Errors;

use Illuminate\Http\JsonResponse;

/**
 * Serializa um {@see BinanceErrorCode} no envelope JSON da sua {@see ErrorFamily} —
 * o único ponto do módulo que sabe montar as duas formas de erro da Binance.
 */
final readonly class ErrorResponseFactory
{
    public function make(ErrorFamily $family, BinanceErrorCode $code, ?string $message = null): JsonResponse
    {
        $message ??= $code->defaultMessage();

        return match ($family) {
            ErrorFamily::SpotWallet => new JsonResponse([
                'code' => $code->value,
                'msg' => $message,
            ], $code->httpStatus()),
            ErrorFamily::Fiat => new JsonResponse([
                'code' => (string) $code->value,
                'message' => $message,
                'success' => false,
                'data' => null,
            ], $code->httpStatus()),
        };
    }
}
