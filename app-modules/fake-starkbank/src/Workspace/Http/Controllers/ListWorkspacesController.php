<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Workspace\Http\Controllers;

use He4rt\FakeStarkbank\Support\StarkbankLog;
use He4rt\FakeStarkbank\Workspace\Actions\ListWorkspaces;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /v2/workspace` — a assinatura é verificada pelo middleware
 * `fake-starkbank.signed` na definição da rota, nunca aqui.
 *
 * O cursor deste endpoint viaja em `cursor` (o único do fake-starkbank assim;
 * as demais listas usam `after`), e é aceito só por fidelidade de wire: o fake
 * serve uma página única e sempre devolve `cursor: null`.
 */
final readonly class ListWorkspacesController
{
    public function __construct(private ListWorkspaces $workspaces) {}

    public function __invoke(Request $request): JsonResponse
    {
        if ($request->query('cursor') !== null) {
            StarkbankLog::debug('fake-starkbank.workspace: cursor recebido e ignorado — a listagem é página única e sempre encerra em cursor null', [
                'cursor' => $request->query('cursor'),
            ]);
        }

        return response()->json([
            'workspaces' => $this->workspaces->handle(),
            'cursor' => null,
        ]);
    }
}
