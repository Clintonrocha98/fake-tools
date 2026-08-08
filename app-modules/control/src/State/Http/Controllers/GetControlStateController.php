<?php

declare(strict_types=1);

namespace He4rt\Control\State\Http\Controllers;

use He4rt\Control\State\Actions\BuildControlStateSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /control/state` — uma request e o dev vê onde os dois fakes estão agora.
 * É o que substitui o `docker exec … tinker`.
 *
 * Observar não pode mudar o estado: a Action lê os Models direto, nunca as
 * Actions de leitura que fazem o avanço lazy.
 */
final readonly class GetControlStateController
{
    public function __construct(private BuildControlStateSnapshot $snapshot) {}

    public function __invoke(Request $request): JsonResponse
    {
        $limite = $request->query('limit');

        return response()->json(
            $this->snapshot->handle(is_numeric($limite) ? max(1, (int) $limite) : null)->toArray()
        );
    }
}
