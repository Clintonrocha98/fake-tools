<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Invoice\Actions\PlanNextInvoice;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Scenarios\Actions\ArmScenario;
use He4rt\FakeStarkbank\Scenarios\Actions\ConsumeArmedScenario;
use He4rt\FakeStarkbank\Scenarios\Actions\DisarmScenario;
use He4rt\FakeStarkbank\Scenarios\Actions\GetArmedScenario;
use He4rt\FakeStarkbank\Scenarios\DTOs\PixScenarioPayload;
use He4rt\FakeStarkbank\Scenarios\Enums\InvoiceOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Enums\TransferOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\WebhookOutcome;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;

/*
|--------------------------------------------------------------------------
| Armar, ler e consumir um cenário da malha PIX
|--------------------------------------------------------------------------
|
| O mecanismo em si, sem nenhuma perna: um armado por perna, vale uma vez, e a
| leitura da UI nunca o gasta.
|
*/

it('arma um desfecho com o parâmetro que ele usa', function (): void {
    $armed = new ArmScenario()->handle(
        InvoiceOutcome::DelayPaid,
        new PixScenarioPayload(extraSeconds: 120),
    );

    expect($armed->leg)->toBe(PixLeg::StarkbankInvoice)
        ->and($armed->resolvedOutcome())->toBe(InvoiceOutcome::DelayPaid)
        ->and($armed->payload->extraSeconds)->toBe(120)
        ->and($armed->armed_at)->not->toBeNull();
});

it('substitui o armado da perna em vez de empilhar um segundo', function (): void {
    $arm = new ArmScenario();

    $arm->handle(InvoiceOutcome::DelayPaid, new PixScenarioPayload(extraSeconds: 120));
    $arm->handle(InvoiceOutcome::Cancel);

    expect(ArmedScenario::query()->count())->toBe(1)
        ->and(ArmedScenario::query()->sole()->resolvedOutcome())->toBe(InvoiceOutcome::Cancel);
});

it('arma a mesma perna duas vezes seguidas sem nunca violar o índice único de leg', function (): void {
    $arm = new ArmScenario();

    $first = $arm->handle(InvoiceOutcome::Overdue);
    $second = $arm->handle(InvoiceOutcome::Expire);

    expect(ArmedScenario::query()->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and(ArmedScenario::query()->sole()->resolvedOutcome())->toBe(InvoiceOutcome::Expire);
});

it('mantém pernas diferentes armadas ao mesmo tempo sem se atrapalharem', function (): void {
    $arm = new ArmScenario();

    $arm->handle(InvoiceOutcome::Cancel);
    $arm->handle(TransferOutcome::Hold);
    $arm->handle(WebhookOutcome::HoldNext);

    expect(ArmedScenario::query()->count())->toBe(3)
        ->and(new GetArmedScenario()->handle(PixLeg::StarkbankInvoice)?->resolvedOutcome())->toBe(InvoiceOutcome::Cancel)
        ->and(new GetArmedScenario()->handle(PixLeg::StarkbankTransfer)?->resolvedOutcome())->toBe(TransferOutcome::Hold)
        ->and(new GetArmedScenario()->handle(PixLeg::StarkbankWebhook)?->resolvedOutcome())->toBe(WebhookOutcome::HoldNext);
});

it('desarma a perna sem deixar nada para trás', function (): void {
    new ArmScenario()->handle(InvoiceOutcome::Cancel);

    new DisarmScenario()->handle(PixLeg::StarkbankInvoice);

    expect(ArmedScenario::query()->count())->toBe(0);
});

it('desarmar uma perna nunca armada é no-op silencioso', function (): void {
    new DisarmScenario()->handle(PixLeg::StarkbankWebhook);

    expect(ArmedScenario::query()->count())->toBe(0);
});

it('lê o armado sem consumi-lo', function (): void {
    new ArmScenario()->handle(InvoiceOutcome::Cancel);

    $read = new GetArmedScenario()->handle(PixLeg::StarkbankInvoice);

    expect($read)->not->toBeNull()
        ->and(ArmedScenario::query()->count())->toBe(1);
});

it('consome o armado exatamente uma vez — a leitura seguinte não encontra nada', function (): void {
    new ArmScenario()->handle(InvoiceOutcome::Cancel);
    $consume = new ConsumeArmedScenario();

    $first = $consume->handle(PixLeg::StarkbankInvoice);
    $second = $consume->handle(PixLeg::StarkbankInvoice);

    expect($first)->not->toBeNull()
        ->and($first->resolvedOutcome())->toBe(InvoiceOutcome::Cancel)
        ->and($second)->toBeNull()
        ->and(ArmedScenario::query()->count())->toBe(0);
});

it('consumir uma perna não gasta o armado de outra', function (): void {
    $arm = new ArmScenario();
    $arm->handle(InvoiceOutcome::Cancel);
    $arm->handle(TransferOutcome::Hold);

    new ConsumeArmedScenario()->handle(PixLeg::StarkbankInvoice);

    expect(new GetArmedScenario()->handle(PixLeg::StarkbankTransfer))->not->toBeNull()
        ->and(new GetArmedScenario()->handle(PixLeg::StarkbankInvoice))->toBeNull();
});

it('devolve null quando a perna nunca foi armada', function (): void {
    expect(new ConsumeArmedScenario()->handle(PixLeg::StarkbankBrcodePayment))->toBeNull()
        ->and(new GetArmedScenario()->handle(PixLeg::StarkbankBrcodePayment))->toBeNull();
});

it('trata um desfecho obsoleto na coluna como nada armado', function (): void {
    // Edição manual ou caso removido do enum: quem planeja cai no plano neutro
    // em vez de estourar dentro do POST do consumidor.
    ArmedScenario::factory()->create([
        'leg' => PixLeg::StarkbankInvoice,
        'outcome' => 'desfecho_que_nao_existe_mais',
    ]);

    expect(new GetArmedScenario()->handle(PixLeg::StarkbankInvoice)?->resolvedOutcome())->toBeNull();
});

it('dá o desvio a exatamente um de dois pedidos na mesma perna — o outro leva o plano neutro', function (): void {
    // O `lockForUpdate()` de ConsumeArmedScenario é o que faz "vale uma vez"
    // valer sob concorrência: dois pedidos serializam na mesma linha e só o
    // primeiro leva o cenário.
    new ArmScenario()->handle(InvoiceOutcome::Cancel);
    $plan = new PlanNextInvoice();

    $primeiro = $plan->handle();
    $segundo = $plan->handle();

    expect($primeiro->isNeutral())->toBeFalse()
        ->and($primeiro->destinedStatus)->toBe(InvoiceStatus::Canceled)
        ->and($segundo->isNeutral())->toBeTrue()
        ->and(ArmedScenario::query()->count())->toBe(0);
});
