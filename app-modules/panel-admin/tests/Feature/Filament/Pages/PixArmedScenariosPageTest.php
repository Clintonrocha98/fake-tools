<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Tests\Feature\Filament;

use Filament\Facades\Filament;
use He4rt\FakeStarkbank\Scenarios\Enums\BrcodePaymentOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\InvoiceOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Enums\TransferOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\WebhookOutcome;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Pages\PixArmedScenariosPage;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs(User::factory()->create());
    auth()->user()->assignRole(Roles::SuperAdmin->value);
});

it('renderiza com todo switch desligado quando nada está armado', function (): void {
    $component = livewire(PixArmedScenariosPage::class)->assertOk();

    foreach (PixLeg::cases() as $leg) {
        foreach ($leg->outcomes() as $outcome) {
            $component->assertSet('data.'.$leg->value.'.'.$outcome->value, false);
        }
    }
});

it('arma o desfecho quando o switch é ligado', function (): void {
    livewire(PixArmedScenariosPage::class)
        ->set('data.starkbank_invoice.cancel', value: true)
        ->assertNotified();

    $armed = ArmedScenario::query()->sole();

    expect($armed->leg)->toBe(PixLeg::StarkbankInvoice)
        ->and($armed->resolvedOutcome())->toBe(InvoiceOutcome::Cancel);
});

it('desliga o desfecho anterior da mesma perna', function (): void {
    livewire(PixArmedScenariosPage::class)
        ->set('data.starkbank_invoice.cancel', value: true)
        ->set('data.starkbank_invoice.overdue', value: true)
        ->assertSet('data.starkbank_invoice.cancel', value: false);

    expect(ArmedScenario::query()->count())->toBe(1)
        ->and(ArmedScenario::query()->sole()->resolvedOutcome())->toBe(InvoiceOutcome::Overdue);
});

it('mantém pernas diferentes armadas ao mesmo tempo', function (): void {
    livewire(PixArmedScenariosPage::class)
        ->set('data.starkbank_invoice.cancel', value: true)
        ->set('data.starkbank_transfer.hold', value: true)
        ->set('data.starkbank_brcode_payment.hold', value: true)
        ->set('data.starkbank_webhook.hold_next', value: true);

    expect(ArmedScenario::query()->count())->toBe(4);
});

it('desarma a perna quando o switch é desligado de volta', function (): void {
    $page = livewire(PixArmedScenariosPage::class)
        ->set('data.starkbank_transfer.hold', value: true);

    // Prova a primeira transição: sem isto, um `set(false)` num banco que já
    // começa vazio passaria mesmo que a página nunca tivesse armado nada.
    expect(ArmedScenario::query()->count())->toBe(1);

    $page->set('data.starkbank_transfer.hold', value: false);

    expect(ArmedScenario::query()->count())->toBe(0);

    livewire(PixArmedScenariosPage::class)
        ->assertSet('data.starkbank_transfer.hold', value: false);
});

it('arma a recusa com o motivo digitado ao lado do switch', function (): void {
    livewire(PixArmedScenariosPage::class)
        ->set('data.starkbank_transfer.reason', 'Conta do favorecido encerrada')
        ->set('data.starkbank_transfer.fail', value: true);

    expect(ArmedScenario::query()->sole()->payload->reason)->toBe('Conta do favorecido encerrada')
        ->and(ArmedScenario::query()->sole()->resolvedOutcome())->toBe(TransferOutcome::Fail);
});

it('re-arma com o motivo novo quando ele é editado com o switch já ligado', function (): void {
    $page = livewire(PixArmedScenariosPage::class)
        ->set('data.starkbank_brcode_payment.reason', 'Saldo insuficiente')
        ->set('data.starkbank_brcode_payment.fail', value: true);

    expect(ArmedScenario::query()->sole()->payload->reason)->toBe('Saldo insuficiente');

    $page->set('data.starkbank_brcode_payment.reason', 'Banco fora do ar')->assertNotified();

    expect(ArmedScenario::query()->sole()->payload->reason)->toBe('Banco fora do ar')
        ->and(ArmedScenario::query()->sole()->resolvedOutcome())->toBe(BrcodePaymentOutcome::Fail);
});

it('arma o atraso com os segundos digitados ao lado do switch', function (): void {
    livewire(PixArmedScenariosPage::class)
        ->set('data.starkbank_invoice.extraSeconds', '900')
        ->set('data.starkbank_invoice.delay_paid', value: true);

    expect(ArmedScenario::query()->sole()->payload->extraSeconds)->toBe(900)
        ->and(ArmedScenario::query()->sole()->resolvedOutcome())->toBe(InvoiceOutcome::DelayPaid);
});

it('não arma nada quando um parâmetro é editado com todos os switches desligados', function (): void {
    livewire(PixArmedScenariosPage::class)
        ->set('data.starkbank_invoice.extraSeconds', '900')
        ->set('data.starkbank_transfer.reason', 'Qualquer coisa');

    expect(ArmedScenario::query()->count())->toBe(0);
});

it('nunca arma um atraso fora da faixa aceitável, venha ele de onde vier', function (string $extraSeconds): void {
    // O input tem `min`, mas ele só vale no navegador: esta página não tem
    // submit, então nada roda a validação do schema. A garantia dura é do VO —
    // fora da faixa é lido como ausente e o desfecho cai no seu default.
    livewire(PixArmedScenariosPage::class)
        ->set('data.starkbank_invoice.extraSeconds', $extraSeconds)
        ->set('data.starkbank_invoice.delay_paid', value: true);

    expect(ArmedScenario::query()->sole()->payload->extraSeconds)->toBeNull();
})->with(['-30', '999999']);

it('reflete no mount o desfecho que já estava armado', function (): void {
    livewire(PixArmedScenariosPage::class)
        ->set('data.starkbank_webhook.corrupt_signature_next', value: true);

    livewire(PixArmedScenariosPage::class)
        ->assertSet('data.starkbank_webhook.corrupt_signature_next', value: true)
        ->assertSet('data.starkbank_webhook.'.WebhookOutcome::HoldNext->value, value: false);
});

it('arma pelo painel cada desfecho de cada perna, sem exceção', function (): void {
    // A tabela de cenários do ticket exige que TODO desfecho seja produzível
    // por switch — um case novo no enum sem toggle correspondente reprova aqui.
    foreach (PixLeg::cases() as $leg) {
        foreach ($leg->outcomes() as $outcome) {
            livewire(PixArmedScenariosPage::class)
                ->set('data.'.$leg->value.'.'.$outcome->value, value: true);

            $armed = ArmedScenario::query()->where('leg', $leg)->sole();

            expect($armed->resolvedOutcome())->toBe($outcome);
        }
    }
});
