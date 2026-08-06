<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Scenarios\Contracts\PixLegOutcomeContract;
use He4rt\FakeStarkbank\Scenarios\Enums\BrcodePaymentOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\InvoiceOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Enums\PixScenarioSwitch;
use He4rt\FakeStarkbank\Scenarios\Enums\TransferOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\WebhookOutcome;

/*
|--------------------------------------------------------------------------
| Vocabulário do subsistema de cenários da malha PIX
|--------------------------------------------------------------------------
|
| A perna é quem amarra cada desfecho ao seu enum. Um desfecho que responda pela
| perna errada faria a UI oferecê-lo no card errado e o plano da outra perna
| consumi-lo sem saber o que fazer com ele.
|
*/

it('lista os desfechos de cada perna sem misturar as famílias', function (PixLeg $leg, array $expected): void {
    expect($leg->outcomes())->toBe($expected);
})->with([
    'invoice' => [PixLeg::StarkbankInvoice, InvoiceOutcome::cases()],
    'transfer' => [PixLeg::StarkbankTransfer, TransferOutcome::cases()],
    'brcode-payment' => [PixLeg::StarkbankBrcodePayment, BrcodePaymentOutcome::cases()],
    'webhook' => [PixLeg::StarkbankWebhook, WebhookOutcome::cases()],
]);

it('faz todo desfecho responder pela perna que o lista', function (PixLeg $leg): void {
    foreach ($leg->outcomes() as $outcome) {
        expect($outcome->leg())->toBe($leg);
    }
})->with([
    'invoice' => [PixLeg::StarkbankInvoice],
    'transfer' => [PixLeg::StarkbankTransfer],
    'brcode-payment' => [PixLeg::StarkbankBrcodePayment],
    'webhook' => [PixLeg::StarkbankWebhook],
]);

it('resolve um desfecho pelo valor dentro da própria perna', function (): void {
    expect(PixLeg::StarkbankInvoice->outcomeFrom('cancel'))->toBe(InvoiceOutcome::Cancel)
        ->and(PixLeg::StarkbankTransfer->outcomeFrom('hold'))->toBe(TransferOutcome::Hold);
});

it('devolve null para um desfecho de outra perna em vez de estourar', function (): void {
    // Um `outcome` obsoleto na coluna faz quem planeja cair no plano neutro,
    // em vez de o fake responder 500 num cenário que ele deveria ignorar.
    expect(PixLeg::StarkbankInvoice->outcomeFrom('duplicate_next'))->toBeNull()
        ->and(PixLeg::StarkbankWebhook->outcomeFrom('cancel'))->toBeNull()
        ->and(PixLeg::StarkbankTransfer->outcomeFrom('inexistente'))->toBeNull();
});

it('declara payloadFields só dos campos que o desfecho de fato usa', function (): void {
    expect(InvoiceOutcome::DelayPaid->payloadFields())->toBe(['extraSeconds'])
        ->and(InvoiceOutcome::Cancel->payloadFields())->toBeEmpty()
        ->and(TransferOutcome::Fail->payloadFields())->toBe(['reason'])
        ->and(TransferOutcome::Hold->payloadFields())->toBeEmpty()
        ->and(BrcodePaymentOutcome::Fail->payloadFields())->toBe(['reason'])
        ->and(WebhookOutcome::HoldNext->payloadFields())->toBeEmpty();
});

it('só admite campo declarado no VO de payload', function (PixLeg $leg): void {
    foreach ($leg->outcomes() as $outcome) {
        expect(array_diff($outcome->payloadFields(), ['reason', 'extraSeconds']))->toBeEmpty();
    }
})->with([
    'invoice' => [PixLeg::StarkbankInvoice],
    'transfer' => [PixLeg::StarkbankTransfer],
    'brcode-payment' => [PixLeg::StarkbankBrcodePayment],
    'webhook' => [PixLeg::StarkbankWebhook],
]);

it('dá a cada switch global a sua coluna no switchboard', function (): void {
    expect(PixScenarioSwitch::Outage->column())->toBe('outage_mode')
        ->and(PixScenarioSwitch::RateLimit->column())->toBe('rate_limit_mode');
});

it('serve rótulo e descrição para todo desfecho, sem case esquecido', function (PixLeg $leg): void {
    foreach ($leg->outcomes() as $outcome) {
        expect($outcome)->toBeInstanceOf(PixLegOutcomeContract::class)
            ->and($outcome->getLabel())->not->toBeEmpty()
            ->and($outcome->getDescription())->not->toBeEmpty();
    }
})->with([
    'invoice' => [PixLeg::StarkbankInvoice],
    'transfer' => [PixLeg::StarkbankTransfer],
    'brcode-payment' => [PixLeg::StarkbankBrcodePayment],
    'webhook' => [PixLeg::StarkbankWebhook],
]);
