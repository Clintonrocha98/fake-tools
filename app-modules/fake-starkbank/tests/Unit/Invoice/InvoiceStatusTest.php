<?php

declare(strict_types=1);

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;

/*
 * O vocabulário de status da invoice: as strings que saem na wire, quem ainda
 * avança sozinho e o evento que cada entrada de estado anuncia.
 */

it('fixa as sete strings de status do StarkBank', function (): void {
    expect(array_map(static fn (InvoiceStatus $status): string => $status->value, InvoiceStatus::cases()))
        ->toEqualCanonicalizing(['created', 'credited', 'paid', 'overdue', 'expired', 'canceled', 'reversed']);
});

it('só deixa created e overdue avançarem sozinhos', function (): void {
    $avancam = array_filter(InvoiceStatus::cases(), static fn (InvoiceStatus $status): bool => $status->advancesAutomatically());

    expect(array_values($avancam))->toBe([InvoiceStatus::Created, InvoiceStatus::Overdue]);
});

it('mapeia cada status para o log type de webhook de mesmo nome', function (InvoiceStatus $status): void {
    expect($status->eventType())->toBeInstanceOf(StarkbankEventType::class)
        ->and($status->eventType()->value)->toBe($status->value);
})->with(InvoiceStatus::cases());

it('emite só log types que a subscription invoice aceita', function (InvoiceStatus $status): void {
    expect(StarkbankSubscription::Invoice->allows($status->eventType()))->toBeTrue();
})->with(InvoiceStatus::cases());

it('implementa os contratos Filament em todos os cases, sem buraco', function (InvoiceStatus $status): void {
    expect($status)->toBeInstanceOf(HasLabel::class)
        ->and($status)->toBeInstanceOf(HasColor::class)
        ->and($status)->toBeInstanceOf(HasDescription::class)
        ->and($status)->toBeInstanceOf(HasIcon::class)
        ->and($status->getLabel())->not->toBeEmpty()
        ->and($status->getColor())->not->toBeEmpty()
        ->and($status->getDescription())->not->toBeEmpty();
})->with(InvoiceStatus::cases());
