<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Scenarios\Actions\ToggleScenarioSwitch;
use He4rt\FakeStarkbank\Scenarios\Enums\PixScenarioSwitch;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;

uses(SignsRequests::class);

/*
|--------------------------------------------------------------------------
| Switches globais aplicados antes de qualquer endpoint /v2/*
|--------------------------------------------------------------------------
|
| A recusa vem ANTES da verificação de assinatura de propósito: é a
| indisponibilidade "antes de qualquer lógica" que o tratamento de erro do
| consumidor precisa exercitar, e um 401 no lugar dela testaria outra coisa.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
});

it('atravessa intocado quando todo switch está desligado', function (): void {
    $this->getSigned('/v2/workspace', $this->signedHeaders())->assertOk();
});

it('derruba a rota com o envelope internalServerError quando o outage está ligado', function (): void {
    new ToggleScenarioSwitch()->handle(PixScenarioSwitch::Outage, enabled: true);

    $this->getSigned('/v2/workspace', $this->signedHeaders())
        ->assertStatus(503)
        ->assertExactJson(['errors' => [['code' => 'internalServerError', 'message' => 'Internal server error']]]);
});

it('derruba mesmo um request sem nenhum header de assinatura', function (): void {
    new ToggleScenarioSwitch()->handle(PixScenarioSwitch::Outage, enabled: true);

    $this->getSigned('/v2/workspace')
        ->assertStatus(503)
        ->assertJsonPath('errors.0.code', 'internalServerError');
});

it('responde 429 com Retry-After quando o rate limit está ligado', function (): void {
    new ToggleScenarioSwitch()->handle(PixScenarioSwitch::RateLimit, enabled: true);

    $this->getSigned('/v2/workspace', $this->signedHeaders())
        ->assertStatus(429)
        ->assertHeader('Retry-After', '30')
        ->assertExactJson(['errors' => [['code' => 'tooManyRequests', 'message' => 'Too many requests']]]);
});

it('derruba toda rota /v2/* do fake, não só a de workspace', function (string $uri): void {
    new ToggleScenarioSwitch()->handle(PixScenarioSwitch::Outage, enabled: true);

    $this->getSigned($uri, $this->signedHeaders())->assertStatus(503);
})->with([
    'workspace' => ['/v2/workspace'],
    'extrato de invoice' => ['/v2/invoice'],
    'extrato de transfer' => ['/v2/transfer'],
    'extrato de brcode-payment' => ['/v2/brcode-payment'],
    'dict' => ['/v2/dict-key/funding@fake-binance.dev'],
    'preview de brcode' => ['/v2/brcode-preview?brcode=qualquer'],
]);

it('outage vence rate limit quando os dois estão ligados', function (): void {
    $toggle = new ToggleScenarioSwitch();
    $toggle->handle(PixScenarioSwitch::Outage, enabled: true);
    $toggle->handle(PixScenarioSwitch::RateLimit, enabled: true);

    $this->getSigned('/v2/workspace', $this->signedHeaders())->assertStatus(503);
});

it('restaura o happy path quando o switch é desligado de volta', function (): void {
    $toggle = new ToggleScenarioSwitch();
    $toggle->handle(PixScenarioSwitch::Outage, enabled: true);
    $toggle->handle(PixScenarioSwitch::Outage, enabled: false);

    $this->getSigned('/v2/workspace', $this->signedHeaders())->assertOk();
});

it('não alcança nenhuma rota do fake-binance — os switchboards são tabelas distintas', function (): void {
    // A independência de blast radius é a razão de o subsistema ser gêmeo em
    // vez de compartilhado: armar outage no StarkBank não pode derrubar o fluxo
    // da venue que já funciona.
    new ToggleScenarioSwitch()->handle(PixScenarioSwitch::Outage, enabled: true);

    $this->getJson('/api/v3/ticker/bookTicker?symbol=USDCBRL')->assertOk();
});
