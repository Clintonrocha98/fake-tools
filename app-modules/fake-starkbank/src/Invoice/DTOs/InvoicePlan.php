<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\DTOs;

use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;

/**
 * O destino que a PRÓXIMA invoice emitida já nasce carregando. O happy path não
 * é um `if` ausente: é o plano neutro (sem destino, sem congelamento, sem
 * atraso), então {@see \He4rt\FakeStarkbank\Invoice\Actions\IssueInvoice} tem
 * UM caminho só e nunca conhece o vocabulário de cenário.
 */
final readonly class InvoicePlan
{
    /** Atraso do desfecho `DelayPaid` quando o operador não informou um. */
    public const int DEFAULT_EXTRA_SECONDS = 300;

    public function __construct(
        public ?InvoiceStatus $destinedStatus = null,
        public bool $freeze = false,
        public int $extraSeconds = 0,
    ) {}

    public static function neutral(): self
    {
        return new self;
    }

    public function isNeutral(): bool
    {
        return !$this->destinedStatus instanceof InvoiceStatus && !$this->freeze && $this->extraSeconds === 0;
    }
}
