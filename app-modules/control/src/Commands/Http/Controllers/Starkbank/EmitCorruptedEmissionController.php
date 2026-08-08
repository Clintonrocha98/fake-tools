<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Starkbank;

use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeStarkbank\Webhook\Actions\EmitCorrupted;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/starkbank/emissions/{emission}/emit-corrupted` — reemite o
 * mesmo evento assinado com um par gerado na hora: o consumidor deve recusar
 * com 401 e não persistir nada.
 *
 * A entidade do envelope sai da emissão original, como no painel — remontá-la
 * aqui seria inventar um payload que nunca existiu.
 */
final readonly class EmitCorruptedEmissionController
{
    public function __construct(private EmitCorrupted $emitCorrupted) {}

    public function __invoke(WebhookEmission $emission): JsonResponse
    {
        $corrompida = ($this->emitCorrupted)(
            $emission->subscription,
            $emission->event_type,
            $emission->entity(),
        );

        return response()->json([
            'emission' => ResourceRows::webhookEmission($corrompida)->toArray(),
        ], 201);
    }
}
