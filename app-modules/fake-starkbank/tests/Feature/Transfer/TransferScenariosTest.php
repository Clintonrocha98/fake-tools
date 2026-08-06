<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Transfer\Actions\AdvanceTransferStatus;
use He4rt\FakeStarkbank\Transfer\Actions\ForceTransferStatus;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

uses(SignsWebhooks::class);

/*
|--------------------------------------------------------------------------
| Cenários da transfer
|--------------------------------------------------------------------------
|
| A Action que o switchboard do painel vai embrulhar num botão: forçar um estado
| ignorando o relógio. Aqui ela existe sozinha — a UI chega no ticket de
| cenários armados.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankWebhook(url: null);
    config(['fake-starkbank-transfer.advance_seconds' => 60]);
});

it('força a recusa, o desfecho que o relógio nunca produz', function (): void {
    $transfer = Transfer::factory()->create();

    $recusada = resolve(ForceTransferStatus::class)->handle($transfer, TransferStatus::Failed);

    expect($recusada->status)->toBe(TransferStatus::Failed)
        ->and(WebhookEmission::query()->firstOrFail()->event_type)->toBe(StarkbankEventType::Failed);
});

it('grava o desfecho em vez de mascará-lo: a leitura seguinte não o desfaz', function (): void {
    $transfer = Transfer::factory()->create();

    resolve(ForceTransferStatus::class)->handle($transfer, TransferStatus::Failed);

    $this->travel(600)->seconds();

    expect(resolve(AdvanceTransferStatus::class)->handle($transfer->refresh())->status)
        ->toBe(TransferStatus::Failed);
});

it('força a devolução sem anunciar evento, porque o fake só emite os dois desfechos do relógio', function (): void {
    // `returned` vira TransferReturned no consumidor quando ele o relê por GET;
    // o gatilho desse ramo é o poll, não um webhook que o StarkBank real
    // emitiria com outro vocabulário.
    $transfer = Transfer::factory()->create();

    $devolvida = resolve(ForceTransferStatus::class)->handle($transfer, TransferStatus::Returned);

    expect($devolvida->status)->toBe(TransferStatus::Returned)
        ->and(WebhookEmission::query()->count())->toBe(0);
});

it('liquida na hora, encurtando a espera pelo avanço lazy', function (): void {
    $transfer = Transfer::factory()->create();

    $liquidada = resolve(ForceTransferStatus::class)->handle($transfer, TransferStatus::Success);

    expect($liquidada->status)->toBe(TransferStatus::Success)
        ->and(WebhookEmission::query()->firstOrFail()->event_type)->toBe(StarkbankEventType::Success);
});
