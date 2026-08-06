<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Tests\Feature\Filament;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Enums\TransferOutcome;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;
use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Resources\StarkbankTransfers\Pages\ListStarkbankTransfers;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs(User::factory()->create());
    auth()->user()->assignRole(Roles::SuperAdmin->value);

    app()->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
});

it('lista as transfers despachadas', function (): void {
    $transfers = Transfer::factory()->count(2)->create();

    livewire(ListStarkbankTransfers::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords($transfers);
});

it('força o desfecho de devolução, que nenhum relógio produz', function (): void {
    $transfer = Transfer::factory()->create(['status' => TransferStatus::Success]);

    livewire(ListStarkbankTransfers::class)
        ->callAction(TestAction::make('forceStatus')->table($transfer), [
            'status' => TransferStatus::Returned->value,
        ])
        ->assertNotified();

    expect($transfer->refresh()->status)->toBe(TransferStatus::Returned);
});

it('arma a recusa da próxima transfer pela ação de cabeçalho', function (): void {
    livewire(ListStarkbankTransfers::class)
        ->callAction('armNextPixLeg', [
            'outcome' => TransferOutcome::Fail->value,
            'reason' => 'Conta do favorecido encerrada',
        ])
        ->assertNotified();

    $armed = ArmedScenario::query()->sole();

    expect($armed->leg)->toBe(PixLeg::StarkbankTransfer)
        ->and($armed->resolvedOutcome())->toBe(TransferOutcome::Fail)
        ->and($armed->payload->reason)->toBe('Conta do favorecido encerrada');
});
