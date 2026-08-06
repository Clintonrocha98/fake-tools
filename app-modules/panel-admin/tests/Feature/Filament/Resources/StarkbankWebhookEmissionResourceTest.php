<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Tests\Feature\Filament;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Enums\WebhookOutcome;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Resources\StarkbankWebhookEmissions\Pages\ListStarkbankWebhookEmissions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use phpseclib3\Crypt\EC;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs(User::factory()->create());
    auth()->user()->assignRole(Roles::SuperAdmin->value);

    config([
        'fake-starkbank.webhook.url' => 'https://consumidor.test/webhooks/starkbank',
        'fake-starkbank.webhook.private_key' => (string) EC::createKey('secp256k1'),
        'fake-starkbank.webhook.private_key_path' => null,
    ]);
});

it('lista as emissões de webhook', function (): void {
    Http::fake();

    $emissions = WebhookEmission::factory()->count(2)->create();

    livewire(ListStarkbankWebhookEmissions::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords($emissions);
});

it('reenvia os bytes gravados com o mesmo event.id', function (): void {
    Http::fake(['https://consumidor.test/*' => Http::response(['status' => 'ok'])]);

    $emission = WebhookEmission::factory()->create(['url' => 'https://consumidor.test/webhooks/starkbank']);

    livewire(ListStarkbankWebhookEmissions::class)
        ->callAction(TestAction::make('replay')->table($emission))
        ->assertNotified();

    Http::assertSent(fn (Request $request): bool => $request->body() === $emission->payload->rawBody);
});

it('dispara uma emissão corrompida a partir de uma já gravada', function (): void {
    Http::fake(['https://consumidor.test/*' => Http::response(['status' => 'ok'])]);

    $emission = WebhookEmission::factory()->create(['url' => 'https://consumidor.test/webhooks/starkbank']);

    livewire(ListStarkbankWebhookEmissions::class)
        ->callAction(TestAction::make('emitCorrupted')->table($emission))
        ->assertNotified();

    expect(WebhookEmission::query()->count())->toBe(2);
});

it('libera a emissão represada e só oferece a ação nela', function (): void {
    Http::fake(['https://consumidor.test/*' => Http::response(['status' => 'ok'])]);

    $represada = WebhookEmission::factory()->held()->create(['url' => 'https://consumidor.test/webhooks/starkbank']);
    $entregue = WebhookEmission::factory()->delivered()->create(['url' => 'https://consumidor.test/webhooks/starkbank']);

    livewire(ListStarkbankWebhookEmissions::class)
        ->assertActionVisible(TestAction::make('releaseHold')->table($represada))
        ->assertActionHidden(TestAction::make('releaseHold')->table($entregue))
        ->callAction(TestAction::make('releaseHold')->table($represada))
        ->assertNotified();

    expect($represada->refresh()->isHeld())->toBeFalse()
        ->and($represada->wasDelivered())->toBeTrue();
});

it('arma o desfecho da próxima emissão pela ação de cabeçalho', function (): void {
    Http::fake();

    livewire(ListStarkbankWebhookEmissions::class)
        ->callAction('armNextPixLeg', ['outcome' => WebhookOutcome::DuplicateNext->value])
        ->assertNotified();

    $armed = ArmedScenario::query()->sole();

    expect($armed->leg)->toBe(PixLeg::StarkbankWebhook)
        ->and($armed->resolvedOutcome())->toBe(WebhookOutcome::DuplicateNext);
});
