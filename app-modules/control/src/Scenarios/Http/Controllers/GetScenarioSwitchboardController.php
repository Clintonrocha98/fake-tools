<?php

declare(strict_types=1);

namespace He4rt\Control\Scenarios\Http\Controllers;

use He4rt\Control\Scenarios\Actions\ResolveScenarioBridge;
use Illuminate\Http\JsonResponse;

/**
 * `GET /control/{fake}/switchboard` — os switches globais DESTE fake. O outro
 * tem switchboard próprio, e é assim de propósito.
 */
final readonly class GetScenarioSwitchboardController
{
    public function __construct(private ResolveScenarioBridge $bridges) {}

    public function __invoke(string $fake): JsonResponse
    {
        return response()->json([
            'fake' => $fake,
            'switchboard' => $this->bridges->handle($fake)->switchboard()->toArray(),
        ]);
    }
}
