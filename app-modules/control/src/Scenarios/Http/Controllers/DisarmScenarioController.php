<?php

declare(strict_types=1);

namespace He4rt\Control\Scenarios\Http\Controllers;

use He4rt\Control\Scenarios\Actions\ResolveScenarioBridge;
use Illuminate\Http\JsonResponse;

/**
 * `DELETE /control/{fake}/scenarios/{leg}` — desarmar o que não estava armado é
 * no-op silencioso, como na Action: o estado final é o mesmo.
 */
final readonly class DisarmScenarioController
{
    public function __construct(private ResolveScenarioBridge $bridges) {}

    public function __invoke(string $fake, string $leg): JsonResponse
    {
        $this->bridges->handle($fake)->disarm($leg);

        return response()->json(['disarmed' => $leg]);
    }
}
