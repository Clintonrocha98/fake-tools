<?php

declare(strict_types=1);

use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Pages\ArmedScenariosPage;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('renders with every spot outcome switch off when nothing is armed', function (): void {
    $component = livewire(ArmedScenariosPage::class)->assertOk();

    foreach (VenueLeg::SpotConversion->outcomes() as $outcome) {
        $component->assertSet('data.spot_conversion.'.$outcome->value, false);
    }
});

it('arms an outcome when its switch is turned on', function (): void {
    livewire(ArmedScenariosPage::class)
        ->set('data.spot_conversion.fill_partial_expired', true)
        ->assertNotified();

    $armed = ArmedScenario::query()->sole();

    expect($armed->leg)->toBe(VenueLeg::SpotConversion)
        ->and($armed->resolvedOutcome())->toBe(SpotConversionOutcome::FillPartialExpired);
});

it('turns off the previously armed outcome of the same leg', function (): void {
    livewire(ArmedScenariosPage::class)
        ->set('data.spot_conversion.fill_partial_expired', true)
        ->set('data.spot_conversion.respond_rejected', true)
        ->assertSet('data.spot_conversion.fill_partial_expired', false);

    expect(ArmedScenario::query()->count())->toBe(1)
        ->and(ArmedScenario::query()->sole()->resolvedOutcome())->toBe(SpotConversionOutcome::RespondRejected);
});

it('disarms the leg when the switch is turned back off', function (): void {
    $page = livewire(ArmedScenariosPage::class)
        ->set('data.spot_conversion.respond_rejected', true);

    // Prova a primeira transição: sem isto, um `set(false)` num banco que já
    // começa vazio passaria mesmo que a página nunca tivesse armado nada.
    expect(ArmedScenario::query()->count())->toBe(1);

    $page->set('data.spot_conversion.respond_rejected', false)
        ->assertSet('data.spot_conversion.respond_rejected', false);

    expect(ArmedScenario::query()->count())->toBe(0);

    // Prova que o desarme também se reflete na UI de um mount novo — não só
    // no estado Livewire que este mesmo teste acabou de setar para false.
    livewire(ArmedScenariosPage::class)
        ->assertSet('data.spot_conversion.respond_rejected', false);
});

it('arms the partial outcome with the fraction typed next to the switch', function (): void {
    livewire(ArmedScenariosPage::class)
        ->set('data.spot_conversion.fraction', '0.25')
        ->set('data.spot_conversion.fill_partial_expired', true);

    expect(ArmedScenario::query()->sole()->payload->fraction)->toBe('0.25');
});
