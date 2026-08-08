<?php

declare(strict_types=1);

namespace He4rt\Control\Scenarios\Http\Controllers;

use He4rt\Control\Scenarios\Actions\ResolveScenarioBridge;
use He4rt\Control\Scenarios\Http\Requests\ToggleScenarioSwitchRequest;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/{fake}/switchboard` — liga/desliga um switch global.
 */
final readonly class ToggleScenarioSwitchController
{
    public function __construct(private ResolveScenarioBridge $bridges) {}

    public function __invoke(ToggleScenarioSwitchRequest $request, string $fake): JsonResponse
    {
        $switchboard = $this->bridges->handle($fake)->toggle(
            $request->switchName(),
            $request->enabled(),
        );

        return response()->json([
            'fake' => $fake,
            'switchboard' => $switchboard->toArray(),
        ]);
    }
}
