<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Console;

use He4rt\FakeStarkbank\Webhook\Actions\DeliverEmission;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * A recuperação de uma janela em que a entrega não saiu (rede fora, consumidor
 * desligado): reenvia toda emissão pendente com os bytes e a assinatura que ela
 * já carrega. Como o `event.id` é o mesmo, o consumidor trata cada reenvio como
 * a mesma entrega — o comando é seguro de rodar quantas vezes o operador
 * quiser.
 *
 * O fake não tem retry automático (decisão do mapa): este comando é o gatilho.
 */
final class FlushWebhooksCommand extends Command
{
    protected $signature = 'fake-starkbank:flush-webhooks';

    protected $description = 'Reenvia as emissões de webhook pendentes (sem sent_at) com o mesmo payload e a mesma assinatura';

    public function handle(DeliverEmission $deliver): int
    {
        /** @var list<WebhookEmission> $pendentes */
        $pendentes = WebhookEmission::query()->pending()->get()->all();

        if ($pendentes === []) {
            $this->info('Nenhuma emissão pendente — nada a reenviar.');

            return self::SUCCESS;
        }

        Log::info('fake-starkbank.webhook: flush disparado — reenviando só as emissões sem sent_at, com os bytes originais', [
            'pendentes' => count($pendentes),
        ]);

        $entregues = 0;

        foreach ($pendentes as $emission) {
            if ($deliver->handle($emission)) {
                $entregues++;
                $this->line(sprintf('Entregue: %s (%s/%s).', $emission->event_id, $emission->subscription->value, $emission->event_type->value));

                continue;
            }

            $this->warn(sprintf('Ainda pendente: %s — %s', $emission->event_id, $emission->refresh()->failed_reason ?? 'sem destino configurado'));
        }

        $this->info(sprintf('Flush concluído: %d de %d entregues.', $entregues, count($pendentes)));

        return self::SUCCESS;
    }
}
