<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\Actions;

use He4rt\FakeStarkbank\Http\Auth\EcdsaSignatureSigner;
use He4rt\FakeStarkbank\Http\Auth\WebhookPrivateKey;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * O POST de uma emissão já montada: manda os BYTES gravados, exatamente como
 * estão, com a assinatura no header `Digital-Signature`. Nunca re-serializa o
 * payload — um `json_encode` diferente do que foi assinado quebra a verificação
 * do consumidor mesmo com dados idênticos.
 *
 * Destino e assinatura são resolvidos TARDE, na entrega, quando a emissão
 * nasceu sem eles: uma emissão gravada num dev sem `FAKE_STARKBANK_WEBHOOK_URL`
 * ou sem PEM legível adota o que estiver configurado agora e sai. Sem isso ela
 * ficaria pendente para sempre — `scopePending` filtra por `sent_at`/`held_at`,
 * não por destino, e todo flush a listaria de novo sem nunca entregá-la.
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

    public function __construct(
        private WebhookPrivateKey $privateKey = new WebhookPrivateKey,
    ) {}

    public function handle(WebhookEmission $emission): bool
    {
        $url = $this->resolveUrl($emission);

        if ($url === '') {
            Log::info('fake-starkbank.webhook: entrega ignorada — emissão sem destino, e nenhuma URL configurada agora para adotar; fica gravada para inspeção no painel', [
                'event_id' => $emission->event_id,
                'subscription' => $emission->subscription->value,
                'event_type' => $emission->event_type->value,
            ]);

            return false;
        }

        $signature = $this->resolveSignature($emission);

        if ($signature === '') {
            return false;
        }

        try {
            $response = Http::withBody($emission->payload->rawBody, 'application/json')
                ->withHeaders(['Digital-Signature' => $signature])
                ->timeout(config()->integer('fake-starkbank.webhook.timeout_seconds', 5))
                ->post($url);
        } catch (Throwable $throwable) {
            $emission->forceFill([
                'response_code' => null,
                'failed_reason' => Str::limit($throwable->getMessage(), self::FAILED_REASON_LIMIT),
            ])->save();

            Log::warning('fake-starkbank.webhook: entrega falhou na rede — sem retry automático, a emissão fica pendente para o flush', [
                'event_id' => $emission->event_id,
                'url' => $url,
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
                'url' => $url,
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

    /**
     * O destino gravado vence sempre; só a emissão que nasceu SEM destino adota
     * o configurado agora. Reavaliar a config de uma emissão que já tem URL
     * faria um replay sair para um endereço diferente do que ela registra.
     */
    private function resolveUrl(WebhookEmission $emission): string
    {
        $gravado = mb_trim($emission->url);

        if ($gravado !== '') {
            return $gravado;
        }

        $configurado = mb_trim((string) config('fake-starkbank.webhook.url', ''));

        if ($configurado === '') {
            return '';
        }

        $emission->forceFill(['url' => $configurado])->save();

        Log::info('fake-starkbank.webhook: destino adotado na entrega — a emissão nasceu sem FAKE_STARKBANK_WEBHOOK_URL e é este flush a janela de recuperação da janela perdida', [
            'event_id' => $emission->event_id,
            'url' => $configurado,
        ]);

        return $configurado;
    }

    /**
     * Mesma regra da URL: a assinatura gravada vence, e só a emissão que nasceu
     * SEM assinatura (PEM ilegível na hora) é assinada agora, sobre os mesmos
     * bytes gravados. Uma emissão deliberadamente corrompida tem assinatura —
     * inválida, mas presente — e nunca passa por aqui.
     */
    private function resolveSignature(WebhookEmission $emission): string
    {
        if ($emission->signature !== '') {
            return $emission->signature;
        }

        $signature = new EcdsaSignatureSigner($this->privateKey->pem())->sign($emission->payload->rawBody);

        if ($signature === null) {
            $emission->forceFill([
                'failed_reason' => Str::limit(WebhookEmission::UNSIGNED_REASON, self::FAILED_REASON_LIMIT),
            ])->save();

            Log::warning('fake-starkbank.webhook: entrega ignorada — emissão sem assinatura e nenhuma chave privada legível para assiná-la agora; um webhook não assinado só viraria 401 do lado de lá', [
                'event_id' => $emission->event_id,
                'subscription' => $emission->subscription->value,
                'event_type' => $emission->event_type->value,
            ]);

            return '';
        }

        $emission->forceFill(['signature' => $signature, 'failed_reason' => null])->save();

        Log::info('fake-starkbank.webhook: emissão assinada na entrega — ela nasceu sem PEM legível, e assinar os bytes já gravados é o que torna o replay possível depois de configurar a chave', [
            'event_id' => $emission->event_id,
        ]);

        return $signature;
    }
}
