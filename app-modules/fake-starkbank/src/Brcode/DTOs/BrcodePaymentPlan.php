<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\DTOs;

use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;

/**
 * O destino que o PRÓXIMO pagamento de BR Code já nasce carregando. O happy
 * path não é um `if` ausente: é o plano neutro (sem destino, sem retenção),
 * então {@see \He4rt\FakeStarkbank\Brcode\Actions\PayBrcode} tem UM caminho só
 * e nunca conhece o vocabulário de cenário.
 */
final readonly class BrcodePaymentPlan
{
    /** Motivo do desfecho `Fail` quando o operador não informou um. */
    public const string DEFAULT_FAILURE_REASON = 'Pagamento recusado pela rede';

    public function __construct(
        public ?BrcodePaymentStatus $destinedStatus = null,
        public bool $held = false,
        public ?string $failureReason = null,
    ) {}

    public static function neutral(): self
    {
        return new self;
    }

    public function isNeutral(): bool
    {
        return !$this->destinedStatus instanceof BrcodePaymentStatus && !$this->held;
    }
}
