<?php

declare(strict_types=1);

namespace He4rt\Control\Http;

use Illuminate\Http\JsonResponse;

/**
 * O envelope de erro do plano de controle. Deliberadamente NÃO é o envelope de
 * nenhum dos dois fakes: aqui quem lê é o dev, não o SDK do consumidor, e imitar
 * o erro da Binance ou do StarkBank numa rota `/control` faria o leitor procurar
 * o defeito no lugar errado.
 */
final readonly class ControlErrorResponse
{
    /**
     * @param  list<string>  $valid
     */
    public static function make(string $message, int $status, array $valid = []): JsonResponse
    {
        return response()->json(array_filter([
            'error' => $message,
            'valid' => $valid === [] ? null : $valid,
        ], static fn (mixed $valor): bool => $valor !== null), $status);
    }
}
