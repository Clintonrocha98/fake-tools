<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;

/*
 * Guarda exaustiva: todo status/tipo que o fake é capaz de servir precisa ter
 * par no vocabulário que o consumidor fala — copiado VERBATIM do código lido
 * nesta issue (`brd-digital/app-modules/integration-starkbank`):
 *
 *   Brd\IntegrationStarkbank\Webhooks\StarkbankEventType::cases()
 *   Brd\IntegrationStarkbank\Settlement\InvoiceSettlementMapper::kindForStatus()
 *   Brd\IntegrationStarkbank\Settlement\TransferSettlementMapper::kindForStatus()
 *   Brd\IntegrationStarkbank\Settlement\BrcodePaymentSettlementMapper::kindForStatus()
 *
 * Se o fake aprender um status ou tipo novo que nenhuma destas listas
 * reconhece, este teste quebra ANTES do consumidor quebrar em runtime contra
 * o StarkBank real.
 */

/**
 * @return list<string>
 */
function consumerStarkbankEventTypeVocabulary(): array
{
    return [
        'created', 'credited', 'paid', 'overdue', 'expired', 'canceled', 'reversed',
        'sending', 'processing', 'success', 'failed', 'unknown',
    ];
}

function consumerInvoiceSettlementKind(string $status): ?string
{
    return match ($status) {
        'paid' => 'ChargePaid',
        'expired' => 'ChargeExpired',
        'canceled' => 'ChargeFailed',
        default => null,
    };
}

function consumerTransferSettlementKind(string $status): ?string
{
    return match ($status) {
        'success' => 'TransferSettled',
        'failed', 'returned' => 'TransferReturned',
        default => null,
    };
}

function consumerBrcodePaymentSettlementKind(string $status): ?string
{
    return match ($status) {
        'success' => 'PaymentSettled',
        'failed', 'returned' => 'PaymentReturned',
        default => null,
    };
}

it('emite, em StarkbankEventType, só vocabulário que o consumidor reconhece', function (): void {
    $fakeEmite = array_map(fn (StarkbankEventType $tipo): string => $tipo->value, StarkbankEventType::cases());

    // O fake nunca fala `unknown`: é fallback de leitura do lado do consumidor,
    // nunca um tipo que o fake decidiria emitir.
    expect($fakeEmite)->toEqualCanonicalizing([
        'created', 'credited', 'paid', 'overdue', 'expired', 'canceled', 'reversed',
        'sending', 'processing', 'success', 'failed',
    ]);

    foreach ($fakeEmite as $valor) {
        expect(consumerStarkbankEventTypeVocabulary())->toContain($valor);
    }
});

it('emite, em InvoiceStatus, só status que o InvoiceSettlementMapper do consumidor reconhece', function (): void {
    foreach (InvoiceStatus::cases() as $status) {
        expect(consumerStarkbankEventTypeVocabulary())->toContain($status->eventType()->value);
    }

    $esperado = [
        InvoiceStatus::Created->value => null,
        InvoiceStatus::Credited->value => null,
        InvoiceStatus::Paid->value => 'ChargePaid',
        InvoiceStatus::Overdue->value => null,
        InvoiceStatus::Expired->value => 'ChargeExpired',
        InvoiceStatus::Canceled->value => 'ChargeFailed',
        InvoiceStatus::Reversed->value => null,
    ];

    foreach (InvoiceStatus::cases() as $status) {
        expect(consumerInvoiceSettlementKind($status->value))->toBe($esperado[$status->value]);
    }
});

it('emite, em TransferStatus, só status que o TransferSettlementMapper do consumidor reconhece', function (): void {
    $esperado = [
        TransferStatus::Created->value => null,
        TransferStatus::Processing->value => null,
        TransferStatus::Success->value => 'TransferSettled',
        TransferStatus::Failed->value => 'TransferReturned',
        TransferStatus::Returned->value => 'TransferReturned',
    ];

    foreach (TransferStatus::cases() as $status) {
        expect(consumerTransferSettlementKind($status->value))->toBe($esperado[$status->value]);
    }

    // Só os dois desfechos que o mapper classifica saem por webhook.
    foreach (TransferStatus::cases() as $status) {
        $tipo = $status->eventType();

        if ($tipo instanceof StarkbankEventType) {
            expect(consumerStarkbankEventTypeVocabulary())->toContain($tipo->value)
                ->and(consumerTransferSettlementKind($status->value))->not->toBeNull();
        }
    }
});

it('emite, em BrcodePaymentStatus, só status que o BrcodePaymentSettlementMapper do consumidor reconhece', function (): void {
    $esperado = [
        BrcodePaymentStatus::Created->value => null,
        BrcodePaymentStatus::Processing->value => null,
        BrcodePaymentStatus::Success->value => 'PaymentSettled',
        BrcodePaymentStatus::Failed->value => 'PaymentReturned',
    ];

    foreach (BrcodePaymentStatus::cases() as $status) {
        expect(consumerBrcodePaymentSettlementKind($status->value))->toBe($esperado[$status->value]);
    }

    foreach (BrcodePaymentStatus::cases() as $status) {
        $tipo = $status->eventType();

        if ($tipo instanceof StarkbankEventType) {
            expect(consumerStarkbankEventTypeVocabulary())->toContain($tipo->value)
                ->and(consumerBrcodePaymentSettlementKind($status->value))->not->toBeNull();
        }
    }
});
