<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\DTOs;

/**
 * O que a PRÓXIMA emissão de evento deve fazer de diferente. O happy path é o
 * plano neutro (assina com a chave de verdade, entrega uma vez, não represa),
 * então {@see \He4rt\FakeStarkbank\Webhook\Actions\EmitWebhookEvent} tem UM
 * caminho só e nunca conhece o vocabulário de cenário.
 */
final readonly class WebhookEmissionPlan
{
    public function __construct(
        public bool $duplicate = false,
        public bool $corruptSignature = false,
        public bool $held = false,
    ) {}

    public static function neutral(): self
    {
        return new self;
    }

    public function isNeutral(): bool
    {
        return !$this->duplicate && !$this->corruptSignature && !$this->held;
    }
}
