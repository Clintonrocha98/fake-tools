<?php

declare(strict_types=1);

namespace He4rt\Control\Scenarios\Http\Controllers;

use He4rt\Control\Scenarios\Actions\ResolveScenarioBridge;
use He4rt\Control\Scenarios\DTOs\LegView;
use Illuminate\Http\JsonResponse;

/**
 * `GET /control/{fake}/scenarios` — toda perna do fake com os desfechos que ela
 * aceita e o que estiver armado nela. Leitura sem consumo: quem gasta o cenário
 * é o pedido de negócio, nunca esta rota.
 */
final readonly class ListScenariosController
{
    public function __construct(private ResolveScenarioBridge $bridges) {}

    public function __invoke(string $fake): JsonResponse
    {
        return response()->json([
            'fake' => $fake,
            'legs' => array_map(
                static fn (LegView $leg): array => $leg->toArray(),
                $this->bridges->handle($fake)->legs(),
            ),
        ]);
    }
}
