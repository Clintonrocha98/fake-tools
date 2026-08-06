<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;

it('emite as três subscriptions do fluxo forex e nenhuma outra', function (): void {
    // `deposit` fica de fora de propósito: o cash-in daqui é sempre por invoice.
    expect(array_map(fn (StarkbankSubscription $s): string => $s->value, StarkbankSubscription::cases()))
        ->toBe(['invoice', 'transfer', 'brcode-payment']);
});

it('mapeia a subscription brcode-payment para a key payment do log', function (): void {
    expect(StarkbankSubscription::Invoice->logKey())->toBe('invoice')
        ->and(StarkbankSubscription::Transfer->logKey())->toBe('transfer')
        ->and(StarkbankSubscription::BrcodePayment->logKey())->toBe('payment');
});

it('conhece o ciclo de vida de cada perna', function (): void {
    expect(array_map(fn (StarkbankEventType $t): string => $t->value, StarkbankSubscription::Invoice->allowedEventTypes()))
        ->toBe(['created', 'credited', 'paid', 'overdue', 'expired', 'canceled', 'reversed'])
        ->and(array_map(fn (StarkbankEventType $t): string => $t->value, StarkbankSubscription::Transfer->allowedEventTypes()))
        ->toBe(['sending', 'processing', 'success', 'failed'])
        ->and(array_map(fn (StarkbankEventType $t): string => $t->value, StarkbankSubscription::BrcodePayment->allowedEventTypes()))
        ->toBe(['sending', 'processing', 'success', 'failed']);
});

it('recusa um tipo de cash-out numa subscription de cash-in', function (): void {
    expect(StarkbankSubscription::Invoice->allows(StarkbankEventType::Paid))->toBeTrue()
        ->and(StarkbankSubscription::Invoice->allows(StarkbankEventType::Success))->toBeFalse()
        ->and(StarkbankSubscription::Transfer->allows(StarkbankEventType::Paid))->toBeFalse();
});

it('fala o mesmo vocabulário de log type que o consumidor', function (): void {
    // Gêmeo do enum do monolito menos o `Unknown`, que lá é fallback de leitura.
    expect(array_map(fn (StarkbankEventType $t): string => $t->value, StarkbankEventType::cases()))
        ->toBe(['created', 'credited', 'paid', 'overdue', 'expired', 'canceled', 'reversed', 'sending', 'processing', 'success', 'failed'])
        ->and(StarkbankEventType::tryFrom('unknown'))->toBeNull();
});

it('classifica os desfechos terminais que o consumidor relê por GET', function (): void {
    $terminais = array_values(array_map(
        fn (StarkbankEventType $t): string => $t->value,
        array_filter(StarkbankEventType::cases(), fn (StarkbankEventType $t): bool => $t->isTerminal()),
    ));

    expect($terminais)->toBe(['paid', 'expired', 'canceled', 'reversed', 'success', 'failed']);
});

it('dá uma cor própria a cada log type', function (): void {
    $cores = array_map(fn (StarkbankEventType $t): string => serialize($t->getColor()), StarkbankEventType::cases());

    expect(array_unique($cores))->toHaveSameSize(StarkbankEventType::cases());
});

it('dá uma cor própria a cada subscription', function (): void {
    $cores = array_map(fn (StarkbankSubscription $s): string => serialize($s->getColor()), StarkbankSubscription::cases());

    expect(array_unique($cores))->toHaveSameSize(StarkbankSubscription::cases());
});

it('preenche label, descrição e ícone em todos os cases', function (): void {
    foreach (StarkbankEventType::cases() as $eventType) {
        expect($eventType->getLabel())->not->toBeEmpty()
            ->and($eventType->getDescription())->not->toBeEmpty();
    }

    foreach (StarkbankSubscription::cases() as $subscription) {
        expect($subscription->getLabel())->not->toBeEmpty()
            ->and($subscription->getDescription())->not->toBeEmpty();
    }
});
