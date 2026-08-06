<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Tests\Feature\Filament;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Scenarios\Enums\InvoiceOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;
use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Resources\StarkbankInvoices\Pages\ListStarkbankInvoices;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs(User::factory()->create());
    auth()->user()->assignRole(Roles::SuperAdmin->value);

    app()->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
});

it('lista as invoices emitidas', function (): void {
    $invoices = Invoice::factory()->count(2)->create();

    livewire(ListStarkbankInvoices::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords($invoices);
});

it('força um status que nenhum relógio produz', function (): void {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Created]);

    livewire(ListStarkbankInvoices::class)
        ->callAction(TestAction::make('forceStatus')->table($invoice), [
            'status' => InvoiceStatus::Reversed->value,
        ])
        ->assertNotified();

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Reversed);
});

it('congela e descongela a invoice', function (): void {
    $invoice = Invoice::factory()->create(['frozen' => false]);

    livewire(ListStarkbankInvoices::class)
        ->callAction(TestAction::make('toggleFrozen')->table($invoice))
        ->assertNotified();

    expect($invoice->refresh()->frozen)->toBeTrue();
});

it('arma a perna pela ação de cabeçalho, sem sair da listagem', function (): void {
    livewire(ListStarkbankInvoices::class)
        ->callAction('armNextPixLeg', ['outcome' => InvoiceOutcome::DelayPaid->value, 'extraSeconds' => '900'])
        ->assertNotified();

    $armed = ArmedScenario::query()->sole();

    expect($armed->leg)->toBe(PixLeg::StarkbankInvoice)
        ->and($armed->resolvedOutcome())->toBe(InvoiceOutcome::DelayPaid)
        ->and($armed->payload->extraSeconds)->toBe(900);
});

it('desarma a perna quando a ação de cabeçalho é enviada sem desfecho', function (): void {
    ArmedScenario::factory()->create([
        'leg' => PixLeg::StarkbankInvoice,
        'outcome' => InvoiceOutcome::Cancel->value,
    ]);

    livewire(ListStarkbankInvoices::class)
        ->callAction('armNextPixLeg', ['outcome' => null])
        ->assertNotified();

    expect(ArmedScenario::query()->count())->toBe(0);
});
