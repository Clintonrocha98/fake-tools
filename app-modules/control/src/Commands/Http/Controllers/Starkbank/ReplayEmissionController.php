<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Starkbank;

use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeStarkbank\Webhook\Actions\ReplayEmission;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/starkbank/emissions/{emission}/replay` — MESMOS bytes, MESMA
 * assinatura, mesmo `event.id`: é assim que o replay exercita a idempotência do
 * consumidor.
 */
final readonly class ReplayEmissionController
{
    public function __construct(private ReplayEmission $replay) {}

    public function __invoke(WebhookEmission $emission): JsonResponse
    {
        return response()->json([
            'emission' => ResourceRows::webhookEmission(($this->replay)($emission))->toArray(),
        ]);
    }
}
