<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Http\Errors;

use Illuminate\Http\JsonResponse;

/**
 * A ÚNICA fonte do envelope de erro do módulo: `{"errors": [{"code",
 * "message"}]}`, exatamente o que `StarkbankGateway::providerError()` do
 * consumidor lê em `errors.0`. Nenhuma rejeição do fake pode sair por
 * `abort()` ou por uma exception renderizada como HTML — o consumidor cai no
 * fallback "HTTP {status}" e o operador perde o motivo.
 */
final readonly class ErrorResponseFactory
{
    public function make(StarkbankErrorCode $code, ?string $message = null): JsonResponse
    {
        return new JsonResponse([
            'errors' => [
                [
                    'code' => $code->value,
                    'message' => $message ?? $code->defaultMessage(),
                ],
            ],
        ], $code->httpStatus());
    }
}
