<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Scenarios\Actions\GetScenarioSwitchboard;
use He4rt\FakeStarkbank\Scenarios\Actions\ToggleScenarioSwitch;
use He4rt\FakeStarkbank\Scenarios\Enums\PixScenarioSwitch;
use He4rt\FakeStarkbank\Scenarios\Models\ScenarioSwitchboard;

/*
|--------------------------------------------------------------------------
| Switchboard global da malha PIX
|--------------------------------------------------------------------------
|
| Linha única, criada desligada na primeira leitura e persistida em banco — o
| estado sobrevive a restart do container.
|
*/

it('cria a linha singleton na primeira leitura, tudo desligado', function (): void {
    expect(ScenarioSwitchboard::query()->count())->toBe(0);

    $switchboard = new GetScenarioSwitchboard()->handle();

    expect($switchboard->outage_mode)->toBeFalse()
        ->and($switchboard->rate_limit_mode)->toBeFalse()
        ->and($switchboard->rate_limit_retry_after_seconds)->toBe(30)
        ->and(ScenarioSwitchboard::query()->count())->toBe(1);
});

it('nunca cria uma segunda linha em leituras repetidas', function (): void {
    new GetScenarioSwitchboard()->handle();
    new GetScenarioSwitchboard()->handle();

    expect(ScenarioSwitchboard::query()->count())->toBe(1);
});

it('serve sempre a linha do id fixo, mesmo com outra linha já no banco', function (): void {
    // Simula a corrida: uma linha extra, criada com um id qualquer, já existe
    // quando a leitura acontece — a leitura deve sempre pousar na do id fixo.
    ScenarioSwitchboard::factory()->create(['outage_mode' => true]);

    $switchboard = new GetScenarioSwitchboard()->handle();

    expect(ScenarioSwitchboard::query()->count())->toBe(2)
        ->and($switchboard->id)->toBe(GetScenarioSwitchboard::SINGLETON_ID)
        ->and($switchboard->outage_mode)->toBeFalse();
});

it('liga e desliga cada switch de forma independente', function (PixScenarioSwitch $switch, string $column): void {
    $toggle = new ToggleScenarioSwitch();

    expect($toggle->handle($switch, enabled: true)->{$column})->toBeTrue()
        ->and($toggle->handle($switch, enabled: false)->{$column})->toBeFalse();
})->with([
    'outage' => [PixScenarioSwitch::Outage, 'outage_mode'],
    'rate limit' => [PixScenarioSwitch::RateLimit, 'rate_limit_mode'],
]);

it('persiste o toggle na mesma linha singleton', function (): void {
    new ToggleScenarioSwitch()->handle(PixScenarioSwitch::Outage, enabled: true);

    expect(ScenarioSwitchboard::query()->count())->toBe(1)
        ->and(ScenarioSwitchboard::query()->sole()->outage_mode)->toBeTrue();
});
