<?php

declare(strict_types=1);

namespace He4rt\Control\Feed\Actions;

use He4rt\Control\Feed\DTOs\ControlFeedPage;
use He4rt\Control\Feed\Models\ControlEvent;

/**
 * A página do feed a partir de um cursor. Ordem CRESCENTE de `id` — contrasta
 * de propósito com o extrato do fake-starkbank, que vai do mais novo para o
 * mais antigo: um feed por cursor precisa ser append-only na direção do cursor,
 * senão a sidebar não emenda página com página.
 */
final readonly class ReadControlFeed
{
    public function handle(?int $after, int $limit): ControlFeedPage
    {
        /** @var list<ControlEvent> $eventos */
        $eventos = ControlEvent::query()
            ->afterCursor($after)
            ->inFeedOrder()
            ->limit($limit)
            ->get()
            ->all();

        $ultimo = end($eventos);

        return new ControlFeedPage(
            events: $eventos,
            // Página vazia devolve o cursor anterior: rebobinar para zero aqui
            // faria a sidebar reapresentar o feed inteiro a cada poll ocioso.
            cursor: $ultimo instanceof ControlEvent ? $ultimo->id : ($after ?? 0),
        );
    }
}
