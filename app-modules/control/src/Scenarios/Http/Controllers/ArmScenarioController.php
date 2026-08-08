<?php

declare(strict_types=1);

namespace He4rt\Control\Scenarios\Http\Controllers;

use He4rt\Control\Scenarios\Actions\ResolveScenarioBridge;
use He4rt\Control\Scenarios\Http\Requests\ArmScenarioRequest;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/{fake}/scenarios` — arma o desfecho do próximo pedido da
 * perna. Mesma Action que o painel invoca, mesmo efeito e mesmo consumo único.
 */
final readonly class ArmScenarioController
{
    public function __construct(private ResolveScenarioBridge $bridges) {}

    public function __invoke(ArmScenarioRequest $request, string $fake): JsonResponse
    {
        $armado = $this->bridges->handle($fake)->arm(
            $request->leg(),
            $request->outcome(),
            $request->scenarioPayload(),
        );

        return response()->json(['armed' => $armado->toArray()], 201);
    }
}
