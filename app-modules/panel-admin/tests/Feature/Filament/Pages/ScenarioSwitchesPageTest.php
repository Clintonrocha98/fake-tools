<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Tests\Feature\Filament;

use Filament\Facades\Filament;
use He4rt\FakeBinance\Scenarios\Actions\GetScenarioSwitchboard;
use He4rt\FakeBinance\Scenarios\Actions\ToggleScenarioSwitch;
use He4rt\FakeBinance\Scenarios\Enums\ScenarioSwitch;
use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Pages\ScenarioSwitchesPage;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs(User::factory()->create());
    auth()->user()->assignRole(Roles::SuperAdmin->value);
});

it('renders with every switch off by default', function (): void {
    livewire(ScenarioSwitchesPage::class)
        ->assertOk()
        ->assertSee('Modo outage')
        ->assertSee('Modo rate limit')
        ->assertSee('Modo relógio torto');
});

it('turns a global switch on through its toggle', function (): void {
    livewire(ScenarioSwitchesPage::class)
        ->set('data.outage', value: true)
        ->assertNotified();

    expect((new GetScenarioSwitchboard)->handle()->outage_mode)->toBeTrue();
});

it('turns a global switch back off through the same toggle', function (): void {
    (new ToggleScenarioSwitch)->handle(ScenarioSwitch::Outage, enabled: true);

    livewire(ScenarioSwitchesPage::class)
        ->set('data.outage', value: false);

    expect((new GetScenarioSwitchboard)->handle()->outage_mode)->toBeFalse();
});

it('loads each switch already reflecting the persisted state', function (): void {
    (new ToggleScenarioSwitch)->handle(ScenarioSwitch::RateLimit, enabled: true);

    livewire(ScenarioSwitchesPage::class)
        ->assertSet('data.rate_limit', value: true)
        ->assertSet('data.outage', value: false)
        ->assertSet('data.clock_skew', value: false);
});
