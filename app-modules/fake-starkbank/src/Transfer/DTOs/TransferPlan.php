<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\DTOs;

use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;

/**
 * O destino que a PRÓXIMA transfer despachada já nasce carregando. O happy path
 * não é um `if` ausente: é o plano neutro (sem destino, sem retenção), então
 * {@see \He4rt\FakeStarkbank\Transfer\Actions\SendTransfer} tem UM caminho só e
 * nunca conhece o vocabulário de cenário.
 */
final readonly class TransferPlan
{
    /** Motivo do desfecho `Fail` quando o operador não informou um. */
    public const string DEFAULT_FAILURE_REASON = 'Transferência recusada pela rede';

    public function __construct(
        public ?TransferStatus $destinedStatus = null,
        public bool $held = false,
        public ?string $failureReason = null,
    ) {}

    public static function neutral(): self
    {
        return new self;
    }

    public function isNeutral(): bool
    {
        return !$this->destinedStatus instanceof TransferStatus && !$this->held;
    }
}
