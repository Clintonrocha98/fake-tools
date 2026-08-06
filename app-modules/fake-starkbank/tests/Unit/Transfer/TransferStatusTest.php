<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;

/*
 * O vocabulário de status do cash-out: é esta string que o
 * `TransferSettlementMapper` do consumidor lê para decidir o SettlementKind.
 */

it('fixa o valor de wire de cada status', function (): void {
    expect(array_map(static fn (TransferStatus $status): string => $status->value, TransferStatus::cases()))
        ->toBe(['created', 'processing', 'success', 'failed', 'returned']);
});

it('só deixa o relógio agir enquanto o dinheiro não teve desfecho', function (TransferStatus $status, bool $avanca): void {
    expect($status->advancesAutomatically())->toBe($avanca);
})->with([
    'created avança' => [TransferStatus::Created, true],
    'processing avança' => [TransferStatus::Processing, true],
    'success é terminal' => [TransferStatus::Success, false],
    'failed é terminal' => [TransferStatus::Failed, false],
    'returned é terminal' => [TransferStatus::Returned, false],
]);

it('emite evento só nos dois desfechos que o consumidor concilia', function (): void {
    expect(TransferStatus::Success->eventType())->toBe(StarkbankEventType::Success)
        ->and(TransferStatus::Failed->eventType())->toBe(StarkbankEventType::Failed)
        ->and(TransferStatus::Created->eventType())->toBeNull()
        ->and(TransferStatus::Processing->eventType())->toBeNull()
        ->and(TransferStatus::Returned->eventType())->toBeNull();
});

it('só emite tipos que a subscription transfer aceita', function (): void {
    // Um par (subscription, log type) fora do ciclo de vida não quebra o
    // consumidor, mas é vocabulário que o StarkBank real nunca produz.
    foreach (TransferStatus::cases() as $status) {
        $eventType = $status->eventType();

        if (!$eventType instanceof StarkbankEventType) {
            continue;
        }

        expect(StarkbankSubscription::Transfer->allows($eventType))->toBeTrue();
    }
});

it('dá uma cor própria a cada status, porque são desfechos e não uma escala', function (): void {
    $cores = array_map(
        static fn (TransferStatus $status): string => json_encode($status->getColor(), JSON_THROW_ON_ERROR),
        TransferStatus::cases(),
    );

    expect($cores)->toHaveSameSize(array_unique($cores));
});
