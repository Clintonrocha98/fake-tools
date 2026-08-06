<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Tests\Feature\Filament;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Scenarios\Enums\BrcodePaymentOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;
use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Resources\StarkbankBrcodePayments\Pages\ListStarkbankBrcodePayments;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs(User::factory()->create());
    auth()->user()->assignRole(Roles::SuperAdmin->value);

    app()->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
});

it('lista os pagamentos de funding', function (): void {
    $payments = BrcodePayment::factory()->count(2)->create();

    livewire(ListStarkbankBrcodePayments::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords($payments);
});

it('força a recusa do funding, que nenhum relógio produz', function (): void {
    $payment = BrcodePayment::factory()->create(['status' => BrcodePaymentStatus::Processing]);

    livewire(ListStarkbankBrcodePayments::class)
        ->callAction(TestAction::make('forceStatus')->table($payment), [
            'status' => BrcodePaymentStatus::Failed->value,
        ])
        ->assertNotified();

    expect($payment->refresh()->status)->toBe(BrcodePaymentStatus::Failed);
});

it('arma a retenção do próximo pagamento pela ação de cabeçalho', function (): void {
    livewire(ListStarkbankBrcodePayments::class)
        ->callAction('armNextPixLeg', ['outcome' => BrcodePaymentOutcome::Hold->value])
        ->assertNotified();

    $armed = ArmedScenario::query()->sole();

    expect($armed->leg)->toBe(PixLeg::StarkbankBrcodePayment)
        ->and($armed->resolvedOutcome())->toBe(BrcodePaymentOutcome::Hold);
});
