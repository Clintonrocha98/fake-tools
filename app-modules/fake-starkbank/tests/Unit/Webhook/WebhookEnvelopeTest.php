<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Webhook\DTOs\WebhookEnvelope;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;

/**
 * @param  array<string, mixed>  $entity
 */
function envelopeDeTeste(
    StarkbankSubscription $subscription = StarkbankSubscription::Invoice,
    StarkbankEventType $eventType = StarkbankEventType::Paid,
    array $entity = ['id' => '5155165527080960', 'amount' => 10_000, 'status' => 'paid', 'tags' => ['deposit-abc-123']],
): WebhookEnvelope {
    return new WebhookEnvelope(
        eventId: '6741658193526784',
        created: '2026-07-09T12:15:00.000000+00:00',
        workspaceId: '6341320293482496',
        subscription: $subscription,
        logId: '5099055211642880',
        logCreated: '2026-07-09T12:15:00.000000+00:00',
        logType: $eventType,
        entity: $entity,
    );
}

it('monta o envelope com as keys camelCase que o consumidor lê', function (): void {
    $serializado = envelopeDeTeste()->jsonSerialize();

    expect(array_keys($serializado))->toBe(['event'])
        ->and(array_keys($serializado['event']))->toBe(['id', 'created', 'workspaceId', 'subscription', 'log'])
        ->and(array_keys($serializado['event']['log']))->toBe(['id', 'created', 'type', 'invoice'])
        ->and($serializado['event']['subscription'])->toBe('invoice')
        ->and($serializado['event']['log']['type'])->toBe('paid');
});

it('embute a entity de brcode-payment na key payment, não na key da subscription', function (): void {
    // A assimetria é do wire: subscription `brcode-payment`, entity em `payment`.
    $log = envelopeDeTeste(StarkbankSubscription::BrcodePayment, StarkbankEventType::Success)->jsonSerialize()['event']['log'];

    expect($log)->toHaveKey('payment')
        ->and($log)->not->toHaveKey('brcode-payment')
        ->and($log)->not->toHaveKey('invoice');
});

it('embute a entity de transfer na key transfer', function (): void {
    $log = envelopeDeTeste(StarkbankSubscription::Transfer, StarkbankEventType::Success)->jsonSerialize()['event']['log'];

    expect($log)->toHaveKey('transfer');
});

it('serve a entity inteira dentro do log, não só o id', function (): void {
    $invoice = envelopeDeTeste()->jsonSerialize()['event']['log']['invoice'];

    expect($invoice)->toBe(['id' => '5155165527080960', 'amount' => 10_000, 'status' => 'paid', 'tags' => ['deposit-abc-123']]);
});

it('extrai o entityId da entity embutida', function (): void {
    expect(envelopeDeTeste()->entityId())->toBe('5155165527080960');
});

it('devolve entityId vazio quando a entity não carrega id', function (): void {
    expect(envelopeDeTeste(entity: ['amount' => 10_000])->entityId())->toBeEmpty();
});
