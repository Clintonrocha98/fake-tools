<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;

/*
 * O vocabulário de status do funding: é esta string que o
 * `BrcodePaymentSettlementMapper` do consumidor lê para decidir o desfecho.
 */

it('fixa o valor de wire de cada status', function (): void {
    expect(array_map(static fn (BrcodePaymentStatus $status): string => $status->value, BrcodePaymentStatus::cases()))
        ->toBe(['created', 'processing', 'success', 'failed']);
});

it('só deixa o relógio agir enquanto o dinheiro não teve desfecho', function (BrcodePaymentStatus $status, bool $avanca): void {
    expect($status->advancesAutomatically())->toBe($avanca);
})->with([
    'created avança' => [BrcodePaymentStatus::Created, true],
    'processing avança' => [BrcodePaymentStatus::Processing, true],
    'success é terminal' => [BrcodePaymentStatus::Success, false],
    'failed é terminal' => [BrcodePaymentStatus::Failed, false],
]);

it('emite evento só nos dois desfechos que o consumidor concilia', function (): void {
    expect(BrcodePaymentStatus::Success->eventType())->toBe(StarkbankEventType::Success)
        ->and(BrcodePaymentStatus::Failed->eventType())->toBe(StarkbankEventType::Failed)
        ->and(BrcodePaymentStatus::Created->eventType())->toBeNull()
        ->and(BrcodePaymentStatus::Processing->eventType())->toBeNull();
});

it('só emite tipos que a subscription brcode-payment aceita', function (): void {
    foreach (BrcodePaymentStatus::cases() as $status) {
        $eventType = $status->eventType();

        if (!$eventType instanceof StarkbankEventType) {
            continue;
        }

        expect(StarkbankSubscription::BrcodePayment->allows($eventType))->toBeTrue();
    }
});

it('dá uma cor própria a cada status, porque são desfechos e não uma escala', function (): void {
    $cores = array_map(
        static fn (BrcodePaymentStatus $status): string => json_encode($status->getColor(), JSON_THROW_ON_ERROR),
        BrcodePaymentStatus::cases(),
    );

    expect($cores)->toHaveSameSize(array_unique($cores));
});
