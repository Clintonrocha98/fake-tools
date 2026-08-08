<?php

declare(strict_types=1);

use He4rt\FakeBinance\Scenarios\Actions\GetArmedScenario as GetArmedVenueScenario;
use He4rt\FakeBinance\Scenarios\Enums\ScenarioSwitch;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeStarkbank\Scenarios\Actions\ArmScenario as ArmPixScenario;
use He4rt\FakeStarkbank\Scenarios\Actions\GetArmedScenario as GetArmedPixScenario;
use He4rt\FakeStarkbank\Scenarios\Actions\GetScenarioSwitchboard as GetPixSwitchboard;
use He4rt\FakeStarkbank\Scenarios\Contracts\PixLegOutcomeContract;
use He4rt\FakeStarkbank\Scenarios\Enums\InvoiceOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Enums\PixScenarioSwitch;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario as PixArmedScenario;

/*
|--------------------------------------------------------------------------
| /control/{fake}/scenarios e /control/{fake}/switchboard
|--------------------------------------------------------------------------
|
| A metade "dirigir" no que ela tem de mais usado. As Actions são gêmeas nos
| dois módulos, com nomes idênticos e enums próprios — este ticket não unifica
| nada: o `{fake}` da rota escolhe o conjunto, e nenhum código passa a ser
| compartilhado entre os fakes.
|
*/

it('lista as pernas do fake com os desfechos que cada uma aceita', function (): void {
    $resposta = $this->getJson('/control/starkbank/scenarios')->assertOk();

    expect($resposta->json('fake'))->toBe('starkbank')
        ->and($resposta->json('legs'))->toHaveSameSize(PixLeg::cases())
        ->and($resposta->json('legs.0.armed'))->toBeNull()
        ->and($resposta->json('legs.0.outcomes'))->not->toBeEmpty();
});

it('arma um cenário por HTTP e o painel vê o mesmo estado', function (): void {
    $outcome = InvoiceOutcome::DelayPaid;

    $this->postJson('/control/starkbank/scenarios', [
        'leg' => $outcome->leg()->value,
        'outcome' => $outcome->value,
        'payload' => ['extraSeconds' => 120],
    ])
        ->assertCreated()
        ->assertJsonPath('armed.leg', $outcome->leg()->value)
        ->assertJsonPath('armed.outcome', $outcome->value)
        ->assertJsonPath('armed.payload.extraSeconds', 120);

    $armado = new GetArmedPixScenario()->handle(PixLeg::StarkbankInvoice);

    expect($armado)->not->toBeNull()
        ->and($armado->outcome)->toBe($outcome->value)
        ->and($armado->payload->extraSeconds)->toBe(120);
});

it('mostra por HTTP o cenário que o painel armou', function (): void {
    $outcome = InvoiceOutcome::Expire;

    new ArmPixScenario()->handle($outcome);

    $resposta = $this->getJson('/control/starkbank/scenarios')->assertOk();

    $perna = collect((array) $resposta->json('legs'))
        ->firstWhere('leg', PixLeg::StarkbankInvoice->value);

    expect($perna['armed']['outcome'])->toBe($outcome->value)
        ->and($perna['armed']['outcomeLabel'])->toBe($outcome->getLabel());
});

it('substitui o cenário quando a mesma perna é armada de novo', function (): void {
    $this->postJson('/control/starkbank/scenarios', [
        'leg' => PixLeg::StarkbankInvoice->value,
        'outcome' => InvoiceOutcome::Expire->value,
    ])->assertCreated();

    $this->postJson('/control/starkbank/scenarios', [
        'leg' => PixLeg::StarkbankInvoice->value,
        'outcome' => InvoiceOutcome::Cancel->value,
    ])->assertCreated();

    expect(PixArmedScenario::query()->where('leg', PixLeg::StarkbankInvoice)->count())->toBe(1)
        ->and(new GetArmedPixScenario()->handle(PixLeg::StarkbankInvoice)->outcome)
        ->toBe(InvoiceOutcome::Cancel->value);
});

it('descarta do payload o campo que o desfecho escolhido não usa', function (): void {
    // `Expire` não declara nenhum campo em payloadFields(); mandar `extraSeconds`
    // aqui é ruído, e o contrato de outcome é quem diz isso.
    $this->postJson('/control/starkbank/scenarios', [
        'leg' => PixLeg::StarkbankInvoice->value,
        'outcome' => InvoiceOutcome::Expire->value,
        'payload' => ['extraSeconds' => 999],
    ])
        ->assertCreated()
        ->assertJsonPath('armed.payload', []);
});

it('desarma a perna por HTTP', function (): void {
    new ArmPixScenario()->handle(InvoiceOutcome::Expire);

    $this->deleteJson('/control/starkbank/scenarios/'.PixLeg::StarkbankInvoice->value)->assertOk();

    expect(new GetArmedPixScenario()->handle(PixLeg::StarkbankInvoice))->toBeNull();
});

it('trata desarmar o que não estava armado como no-op', function (): void {
    $this->deleteJson('/control/starkbank/scenarios/'.PixLeg::StarkbankInvoice->value)
        ->assertOk()
        ->assertJsonPath('disarmed', PixLeg::StarkbankInvoice->value);
});

it('recusa com 422 e lista os válidos quando o outcome não pertence à perna', function (): void {
    $resposta = $this->postJson('/control/starkbank/scenarios', [
        'leg' => PixLeg::StarkbankInvoice->value,
        'outcome' => SpotConversionOutcome::cases()[0]->value,
    ])->assertStatus(422);

    expect($resposta->json('valid'))->toEqualCanonicalizing(
        array_map(static fn (PixLegOutcomeContract $outcome): string => (string) $outcome->value, PixLeg::StarkbankInvoice->outcomes()),
    );
});

it('recusa com 422 e lista as pernas quando a perna não existe', function (): void {
    $resposta = $this->postJson('/control/starkbank/scenarios', [
        'leg' => 'perna_inventada',
        'outcome' => InvoiceOutcome::Expire->value,
    ])->assertStatus(422);

    expect($resposta->json('valid'))->toEqualCanonicalizing(
        array_map(static fn (PixLeg $leg): string => $leg->value, PixLeg::cases()),
    );
});

it('responde 404 quando o fake não existe', function (): void {
    $this->getJson('/control/mercadopago/scenarios')
        ->assertNotFound()
        ->assertJsonPath('valid', ['starkbank', 'binance']);
});

it('vira um switch do switchboard por HTTP', function (): void {
    $this->postJson('/control/starkbank/switchboard', [
        'switch' => PixScenarioSwitch::Outage->value,
        'enabled' => true,
    ])->assertOk();

    expect(new GetPixSwitchboard()->handle()->outage_mode)->toBeTrue();
});

it('não deixa o switchboard de um fake tocar no do outro', function (): void {
    $this->postJson('/control/binance/switchboard', [
        'switch' => ScenarioSwitch::Outage->value,
        'enabled' => true,
    ])->assertOk();

    $starkbank = $this->getJson('/control/starkbank/switchboard')->assertOk();

    $outage = collect((array) $starkbank->json('switchboard.switches'))
        ->firstWhere('switch', PixScenarioSwitch::Outage->value);

    expect($outage['enabled'])->toBeFalse()
        ->and(new GetPixSwitchboard()->handle()->outage_mode)->toBeFalse();
});

it('recusa com 422 um switch que o fake não conhece', function (): void {
    // `clock_skew` existe no fake-binance e não na malha PIX — a janela de
    // recepção do StarkBank já é exercitada pelo próprio Access-Time.
    $resposta = $this->postJson('/control/starkbank/switchboard', [
        'switch' => ScenarioSwitch::ClockSkew->value,
        'enabled' => true,
    ])->assertStatus(422);

    expect($resposta->json('valid'))->toEqualCanonicalizing(
        array_map(static fn (PixScenarioSwitch $switch): string => $switch->value, PixScenarioSwitch::cases()),
    );
});

it('arma e lê o cenário da venue pelo mesmo desenho de rota', function (): void {
    $outcome = SpotConversionOutcome::cases()[0];

    $this->postJson('/control/binance/scenarios', [
        'leg' => $outcome->leg()->value,
        'outcome' => (string) $outcome->value,
    ])->assertCreated();

    expect(new GetArmedVenueScenario()->handle(VenueLeg::SpotConversion))->not->toBeNull();
});

it('recusa o body sem leg ou sem outcome', function (): void {
    $this->postJson('/control/starkbank/scenarios', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['leg', 'outcome']);
});
