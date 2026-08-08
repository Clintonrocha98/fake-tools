<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FAKE_TOOLS_CONTROL_ENABLED
|--------------------------------------------------------------------------
|
| Desligado, o grupo `/control` nem se registra — 404, não 403. Um grupo
| registrado e barrado por middleware continuaria aparecendo em `route:list` e
| continuaria respondendo alguma coisa.
|
| A env é trocada ANTES de recriar a app: o registro das rotas acontece no
| boot, então mudar só o `config()` num teste não desregistra nada.
|
*/

beforeEach(function (): void {
    putenv('FAKE_TOOLS_CONTROL_ENABLED=false');

    $this->refreshApplication();
});

afterEach(function (): void {
    putenv('FAKE_TOOLS_CONTROL_ENABLED');

    $this->refreshApplication();
});

it('não registra nenhuma rota /control com o kill-switch desligado', function (): void {
    expect(config('control.enabled'))->toBeFalse();

    $rotas = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($rota): bool => str_starts_with((string) $rota->uri(), 'control'));

    expect($rotas)->toBeEmpty();
});

it('responde 404 no feed com o kill-switch desligado', function (): void {
    $this->getJson('/control/feed')->assertNotFound();
});

it('responde 404 no snapshot com o kill-switch desligado', function (): void {
    $this->getJson('/control/state')->assertNotFound();
});

it('responde 404 nos cenários com o kill-switch desligado', function (): void {
    $this->getJson('/control/starkbank/scenarios')->assertNotFound();
    $this->getJson('/control/binance/switchboard')->assertNotFound();
});

it('responde 404 no reset com o kill-switch desligado', function (): void {
    $this->postJson('/control/reset')->assertNotFound();
});
