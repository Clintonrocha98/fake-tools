<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Tests\Feature\Filament;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Pages\ScenarioSwitchesPage;
use He4rt\Venue\Scenarios\Models\ScenarioSwitchboard;

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

it('can turn the outage switch on', function (): void {
    livewire(ScenarioSwitchesPage::class)
        ->callAction(TestAction::make('toggle')->arguments(['switch' => 'outage', 'enable' => true]))
        ->assertNotified();

    expect(ScenarioSwitchboard::query()->first()->outage_mode)->toBeTrue();
});

it('can turn a switch back off', function (): void {
    livewire(ScenarioSwitchesPage::class)
        ->callAction(TestAction::make('toggle')->arguments(['switch' => 'outage', 'enable' => true]))
        ->callAction(TestAction::make('toggle')->arguments(['switch' => 'outage', 'enable' => false]))
        ->assertNotified();

    expect(ScenarioSwitchboard::query()->first()->outage_mode)->toBeFalse();
});
