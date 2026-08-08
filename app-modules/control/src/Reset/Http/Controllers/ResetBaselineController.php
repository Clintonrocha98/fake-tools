<?php

declare(strict_types=1);

namespace He4rt\Control\Reset\Http\Controllers;

use He4rt\Control\Reset\Actions\ResetBaseline;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/reset` — o consumo alvo, porque o ponto do plano de controle é
 * não precisar de `docker exec`.
 */
final readonly class ResetBaselineController
{
    public function __construct(private ResetBaseline $reset) {}

    public function __invoke(): JsonResponse
    {
        return response()->json($this->reset->handle()->toArray());
    }
}
