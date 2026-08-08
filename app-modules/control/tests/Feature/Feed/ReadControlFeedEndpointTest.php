<?php

declare(strict_types=1);

use He4rt\Control\Feed\Models\ControlEvent;
use He4rt\FakeBinance\Support\BinanceLog;

/*
|--------------------------------------------------------------------------
| GET /control/feed
|--------------------------------------------------------------------------
|
| O cursor que a sidebar do consumidor faz poll. Ordem crescente de `id`, e o
| cursor do próximo poll vem pronto na resposta.
|
*/

it('devolve os eventos em ordem crescente de id', function (): void {
    $primeiro = ControlEvent::factory()->binance()->create(['message' => 'um']);
    $segundo = ControlEvent::factory()->starkbank()->create(['message' => 'dois']);

    $this->getJson('/control/feed')
        ->assertOk()
        ->assertJsonPath('events.0.id', $primeiro->id)
        ->assertJsonPath('events.1.id', $segundo->id)
        ->assertJsonPath('count', 2)
        ->assertJsonPath('cursor', $segundo->id);
});

it('filtra estritamente maior que o cursor recebido', function (): void {
    $primeiro = ControlEvent::factory()->create();
    $segundo = ControlEvent::factory()->create();

    $this->getJson('/control/feed?after='.$primeiro->id)
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('events.0.id', $segundo->id);
});

it('devolve o cursor anterior quando a página volta vazia', function (): void {
    $evento = ControlEvent::factory()->create();

    $this->getJson('/control/feed?after='.$evento->id)
        ->assertOk()
        ->assertJsonPath('count', 0)
        ->assertJsonPath('cursor', $evento->id);
});

it('nunca reapresenta o que já foi entregue em dois polls seguidos', function (): void {
    ControlEvent::factory()->count(3)->create();

    $primeiroPoll = $this->getJson('/control/feed')->assertOk();
    $cursor = $primeiroPoll->json('cursor');

    $this->getJson('/control/feed?after='.$cursor)
        ->assertOk()
        ->assertJsonPath('count', 0)
        ->assertJsonPath('cursor', $cursor);
});

it('respeita o teto do limit mesmo quando o cliente pede mais', function (): void {
    config(['control.feed.max_limit' => 2]);

    ControlEvent::factory()->count(5)->create();

    $this->getJson('/control/feed?limit=100')
        ->assertOk()
        ->assertJsonPath('count', 2);
});

it('usa o limit default quando o cliente não pede nada', function (): void {
    config(['control.feed.default_limit' => 3]);

    ControlEvent::factory()->count(5)->create();

    $this->getJson('/control/feed')
        ->assertOk()
        ->assertJsonPath('count', 3);
});

it('recusa um cursor que não é inteiro', function (): void {
    $this->getJson('/control/feed?after=ontem')->assertStatus(422);
});

it('serve cada evento com o contexto, o request_id e o instante real', function (): void {
    $evento = ControlEvent::factory()->binance()->create([
        'level' => 'warning',
        'message' => 'fake-binance.teste: transição',
        'context' => ['order_id' => 7],
        'request_id' => '3f1a5c0e-9a2b-4c3d-8e5f-1a2b3c4d5e6f',
    ]);

    $this->getJson('/control/feed')
        ->assertOk()
        ->assertJsonPath('events.0.channel', 'binance')
        ->assertJsonPath('events.0.level', 'warning')
        ->assertJsonPath('events.0.message', 'fake-binance.teste: transição')
        ->assertJsonPath('events.0.context.order_id', 7)
        ->assertJsonPath('events.0.requestId', '3f1a5c0e-9a2b-4c3d-8e5f-1a2b3c4d5e6f')
        ->assertJsonPath('events.0.occurredAt', $evento->occurred_at->toIso8601String());
});

it('não gera evento no próprio feed ao ser consultado', function (): void {
    BinanceLog::info('fake-binance.teste: uma transição de verdade');

    $antes = ControlEvent::query()->count();

    $this->getJson('/control/feed')->assertOk();
    $this->getJson('/control/feed')->assertOk();

    expect(ControlEvent::query()->count())->toBe($antes);
});
