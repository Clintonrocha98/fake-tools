<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Starkbank;

use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeStarkbank\Webhook\Actions\ReleaseEmissionHold;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/starkbank/emissions/{emission}/release` — liberar o que não
 * estava represado é no-op, como na Action: reentregar ali seria um replay
 * disfarçado.
 */
final readonly class ReleaseEmissionHoldController
{
    public function __construct(private ReleaseEmissionHold $release) {}

    public function __invoke(WebhookEmission $emission): JsonResponse
    {
        return response()->json([
            'emission' => ResourceRows::webhookEmission(($this->release)($emission))->toArray(),
        ]);
    }
}
