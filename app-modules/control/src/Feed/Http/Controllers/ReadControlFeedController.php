<?php

declare(strict_types=1);

namespace He4rt\Control\Feed\Http\Controllers;

use He4rt\Control\Feed\Actions\ReadControlFeed;
use He4rt\Control\Feed\Http\Requests\ReadControlFeedRequest;
use Illuminate\Http\JsonResponse;

/**
 * `GET /control/feed?after=<id>&limit=<n>` — o cursor que a sidebar do
 * consumidor faz poll a cada 1–2s.
 *
 * A rota fica fora de `*.request-log` de propósito: o polling do feed geraria
 * evento no próprio feed, e o ruído enterraria o sinal.
 */
final readonly class ReadControlFeedController
{
    public function __construct(private ReadControlFeed $feed) {}

    public function __invoke(ReadControlFeedRequest $request): JsonResponse
    {
        return response()->json(
            $this->feed->handle($request->cursorAfter(), $request->limit())->toArray()
        );
    }
}
