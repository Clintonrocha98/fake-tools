<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\Actions;

use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * O POST de uma emissão já montada e já assinada: manda os bytes gravados,
 * exatamente como estão, com a `signature` gravada no header
 * `Digital-Signature`. Nunca re-serializa o payload — um `json_encode`
 * diferente do que foi assinado quebra a verificação do consumidor mesmo com
 * dados idênticos.
 *
 * Sem retry automático: o consumidor responde 200 até para falha interna e usa
 * o poll de conciliação como safety net, e o StarkBank real também não retenta
 * sobre 200. A recuperação de uma emissão que não saiu é
 * `fake-starkbank:flush-webhooks` ou o replay manual.
 */
final readonly class DeliverEmission
{
    /**
     * Limite do texto gravado em `failed_reason` — a coluna é um `string`, e o
     * motivo serve para o operador ler, não para carregar um stack trace.
     */
    private const int FAILED_REASON_LIMIT = 200;

    public function handle(WebhookEmission $emission): bool
    {
        if (mb_trim($emission->url) === '') {
            Log::info('fake-starkbank.webhook: entrega ignorada — emissão sem destino configurado, gravada só para inspeção no painel', [
                'event_id' => $emission->event_id,
                'subscription' => $emission->subscription->value,
                'event_type' => $emission->event_type->value,
            ]);

            return false;
        }

        try {
            $response = Http::withBody($emission->payload->rawBody, 'application/json')
                ->withHeaders(['Digital-Signature' => $emission->signature])
                ->timeout(config()->integer('fake-starkbank.webhook.timeout_seconds', 5))
                ->post($emission->url);
        } catch (Throwable $throwable) {
            $emission->forceFill([
                'response_code' => null,
                'failed_reason' => Str::limit($throwable->getMessage(), self::FAILED_REASON_LIMIT),
            ])->save();

            Log::warning('fake-starkbank.webhook: entrega falhou na rede — sem retry automático, a emissão fica pendente para o flush', [
                'event_id' => $emission->event_id,
                'url' => $emission->url,
                'reason' => $throwable->getMessage(),
            ]);

            return false;
        }

        if (!$response->successful()) {
            $emission->forceFill([
                'response_code' => $response->status(),
                'failed_reason' => Str::limit('HTTP '.$response->status().': '.$response->body(), self::FAILED_REASON_LIMIT),
            ])->save();

            Log::warning('fake-starkbank.webhook: consumidor recusou a entrega — 401 aqui costuma ser assinatura que não bate com o PEM público do lado de lá', [
                'event_id' => $emission->event_id,
                'url' => $emission->url,
                'response_code' => $response->status(),
            ]);

            return false;
        }

        $emission->forceFill([
            'response_code' => $response->status(),
            'sent_at' => Date::now(),
            'failed_reason' => null,
        ])->save();

        Log::info('fake-starkbank.webhook: entrega confirmada pelo consumidor — a partir daqui o GET de releitura é a verdade, o webhook só disparou', [
            'event_id' => $emission->event_id,
            'subscription' => $emission->subscription->value,
            'event_type' => $emission->event_type->value,
            'entity_id' => $emission->entity_id,
            'response_code' => $response->status(),
        ]);

        return true;
    }
}
