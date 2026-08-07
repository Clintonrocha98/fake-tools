<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\Jobs;

use He4rt\FakeStarkbank\Support\StarkbankLog;
use He4rt\FakeStarkbank\Webhook\Actions\DeliverEmission;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

/**
 * Carrega a entrega para DEPOIS da resposta
 * ({@see \He4rt\FakeStarkbank\Webhook\Actions\EmitWebhookEvent}). Viaja só o id
 * da emissão: os bytes e a assinatura já estão gravados, e reler a linha na
 * hora do POST evita mandar um payload que o operador acabou de substituir.
 */
final readonly class DeliverWebhookEmission
{
    public function __construct(private string $emissionId) {}

    public function handle(DeliverEmission $deliver): void
    {
        $emission = WebhookEmission::query()->find($this->emissionId);

        if (!$emission instanceof WebhookEmission) {
            StarkbankLog::warning('fake-starkbank.webhook: emissão sumiu antes da entrega pós-resposta — nada foi POSTado', [
                'emission_id' => $this->emissionId,
            ]);

            return;
        }

        $deliver->handle($emission);
    }
}
