<?php

declare(strict_types=1);

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
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
        ->set('data.spot_conversion.fill_partial_expired', value: true)
        ->assertNotified();

    $armed = ArmedScenario::query()->sole();

    expect($armed->leg)->toBe(VenueLeg::SpotConversion)
        ->and($armed->resolvedOutcome())->toBe(SpotConversionOutcome::FillPartialExpired);
});

it('turns off the previously armed outcome of the same leg', function (): void {
    livewire(ArmedScenariosPage::class)
        ->set('data.spot_conversion.fill_partial_expired', value: true)
        ->set('data.spot_conversion.respond_rejected', value: true)
        ->assertSet('data.spot_conversion.fill_partial_expired', value: false);

    expect(ArmedScenario::query()->count())->toBe(1)
        ->and(ArmedScenario::query()->sole()->resolvedOutcome())->toBe(SpotConversionOutcome::RespondRejected);
});

it('disarms the leg when the switch is turned back off', function (): void {
    $page = livewire(ArmedScenariosPage::class)
        ->set('data.spot_conversion.respond_rejected', value: true);

    // Prova a primeira transição: sem isto, um `set(false)` num banco que já
    // começa vazio passaria mesmo que a página nunca tivesse armado nada.
    expect(ArmedScenario::query()->count())->toBe(1);

    $page->set('data.spot_conversion.respond_rejected', value: false)
        ->assertSet('data.spot_conversion.respond_rejected', value: false);

    expect(ArmedScenario::query()->count())->toBe(0);

    // Prova que o desarme também se reflete na UI de um mount novo — não só
    // no estado Livewire que este mesmo teste acabou de setar para false.
    livewire(ArmedScenariosPage::class)
        ->assertSet('data.spot_conversion.respond_rejected', value: false);
});

it('arms the partial outcome with the fraction typed next to the switch', function (): void {
    livewire(ArmedScenariosPage::class)
        ->set('data.spot_conversion.fraction', '0.25')
        ->set('data.spot_conversion.fill_partial_expired', value: true);

    expect(ArmedScenario::query()->sole()->payload->fraction)->toBe('0.25');
});

it('re-arms with the new fraction when it is edited with the switch already on', function (): void {
    $page = livewire(ArmedScenariosPage::class)
        ->set('data.spot_conversion.fraction', '0.5')
        ->set('data.spot_conversion.fill_partial_expired', value: true);

    expect(ArmedScenario::query()->sole()->payload->fraction)->toBe('0.5');

    $page->set('data.spot_conversion.fraction', '0.25')->assertNotified();

    expect(ArmedScenario::query()->sole()->payload->fraction)->toBe('0.25')
        ->and(ArmedScenario::query()->sole()->resolvedOutcome())->toBe(SpotConversionOutcome::FillPartialExpired);
});

it('re-arms with the new error code and the new raw status too', function (): void {
    $page = livewire(ArmedScenariosPage::class)
        ->set('data.spot_conversion.refuse_with_code', value: true)
        ->set('data.spot_conversion.errorCode', (string) BinanceErrorCode::FilterFailure->value);

    expect(ArmedScenario::query()->sole()->payload->binanceErrorCode())->toBe(BinanceErrorCode::FilterFailure);

    $page->set('data.spot_conversion.emit_unknown_status', value: true)
        ->set('data.spot_conversion.rawStatus', 'BANANA');

    expect(ArmedScenario::query()->sole()->payload->rawStatus)->toBe('BANANA')
        ->and(ArmedScenario::query()->sole()->resolvedOutcome())->toBe(SpotConversionOutcome::EmitUnknownStatus);
});

it('arms nothing when a parameter is edited with every switch off', function (): void {
    livewire(ArmedScenariosPage::class)
        ->set('data.spot_conversion.fraction', '0.25')
        ->set('data.spot_conversion.rawStatus', 'BANANA');

    expect(ArmedScenario::query()->count())->toBe(0);
});

it('never arms a fill fraction outside [0, 1], whatever reaches the state', function (string $fraction): void {
    livewire(ArmedScenariosPage::class)
        ->set('data.spot_conversion.fraction', $fraction)
        ->set('data.spot_conversion.fill_partial_expired', value: true);

    // O input tem `min`/`max`, mas eles só valem no navegador: esta página não
    // tem submit, então nada roda a validação do schema. A garantia dura é do
    // VO — fração fora de [0, 1] é lida como ausente e o desfecho cai no seu
    // default, em vez de gravar um executedQty negativo (ou o dobro do pedido).
    expect(ArmedScenario::query()->sole()->payload->fraction)->toBeNull();
})->with(['-0.5', '2']);
