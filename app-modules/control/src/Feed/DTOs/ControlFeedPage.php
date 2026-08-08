<?php

declare(strict_types=1);

namespace He4rt\Control\Feed\DTOs;

use He4rt\Control\Feed\Models\ControlEvent;

/**
 * Uma página do feed e o cursor do próximo poll.
 *
 * O cursor vem pronto de propósito: a sidebar não deve recalculá-lo, porque o
 * caso comum entre dois polls é a página VAZIA — e aí o maior `id` da página
 * não existe. Devolver o cursor anterior nesse caso é o que impede o poll
 * seguinte de rebobinar para o começo.
 */
final readonly class ControlFeedPage
{
    /**
     * @param  list<ControlEvent>  $events
     */
    public function __construct(
        public array $events,
        public int $cursor,
    ) {}

    /**
     * @return array{events: list<array{id: int, channel: string, level: string, message: string, context: array<string, scalar|array<array-key, mixed>|null>, requestId: string|null, occurredAt: string}>, cursor: int, count: int}
     */
    public function toArray(): array
    {
        return [
            'events' => array_map(static fn (ControlEvent $event): array => [
                'id' => $event->id,
                'channel' => $event->channel,
                'level' => $event->level,
                'message' => $event->message,
                'context' => $event->context->toArray(),
                'requestId' => $event->request_id,
                'occurredAt' => $event->occurred_at->toIso8601String(),
            ], $this->events),
            'cursor' => $this->cursor,
            'count' => count($this->events),
        ];
    }
}
