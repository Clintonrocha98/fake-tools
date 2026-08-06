<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Tests\Feature\Filament;

use Filament\Facades\Filament;
use He4rt\FakeBinance\Scenarios\Actions\GetScenarioSwitchboard as GetBinanceSwitchboard;
use He4rt\FakeStarkbank\Scenarios\Actions\GetScenarioSwitchboard;
use He4rt\FakeStarkbank\Scenarios\Actions\ToggleScenarioSwitch;
use He4rt\FakeStarkbank\Scenarios\Enums\PixScenarioSwitch;
use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Pages\PixScenarioSwitchesPage;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs(User::factory()->create());
    auth()->user()->assignRole(Roles::SuperAdmin->value);
});

it('renderiza com os dois switches desligados por padrão', function (): void {
    livewire(PixScenarioSwitchesPage::class)
        ->assertOk()
        ->assertSee('Modo outage')
        ->assertSee('Modo rate limit');
});

it('liga um switch global pelo seu toggle', function (): void {
    livewire(PixScenarioSwitchesPage::class)
        ->set('data.outage', value: true)
        ->assertNotified();

    expect(new GetScenarioSwitchboard()->handle()->outage_mode)->toBeTrue();
});

it('desliga o switch global pelo mesmo toggle', function (): void {
    new ToggleScenarioSwitch()->handle(PixScenarioSwitch::Outage, enabled: true);

    livewire(PixScenarioSwitchesPage::class)
        ->set('data.outage', value: false);

    expect(new GetScenarioSwitchboard()->handle()->outage_mode)->toBeFalse();
});

it('carrega cada switch já refletindo o estado persistido', function (): void {
    new ToggleScenarioSwitch()->handle(PixScenarioSwitch::RateLimit, enabled: true);

    livewire(PixScenarioSwitchesPage::class)
        ->assertSet('data.rate_limit', value: true)
        ->assertSet('data.outage', value: false);
});

it('nunca liga o switchboard do outro fake', function (): void {
    // A tela é separada porque o switchboard é separado: derrubar o StarkBank
    // não pode derrubar a venue que já funciona.
    livewire(PixScenarioSwitchesPage::class)->set('data.outage', value: true);

    expect(new GetBinanceSwitchboard()->handle()->outage_mode)->toBeFalse();
});
