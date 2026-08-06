<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\Actions;

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Http\Auth\EcdsaSignatureSigner;
use He4rt\FakeStarkbank\Http\Auth\WebhookPrivateKey;
use He4rt\FakeStarkbank\Support\NumericId;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\DTOs\WebhookEnvelope;
use He4rt\FakeStarkbank\Webhook\DTOs\WebhookPayload;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use He4rt\FakeStarkbank\Webhook\Jobs\DeliverWebhookEmission;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * O lado ativo do contrato: monta o envelope, assina os bytes com a chave
 * privada do fake, grava a emissão e agenda o POST para o
 * `POST /webhooks/starkbank` do consumidor.
 *
 * A entrega é POSTERIOR à resposta ({@see Bus::dispatchAfterResponse}) porque em
 * dev o consumidor roda `php artisan serve`, single-thread: POSTar de dentro do
 * mesmo request em que ele está chamando o fake trava os dois lados esperando um
 * ao outro.
 *
 * O envelope é serializado UMA vez: os bytes assinados são os bytes gravados e
 * os bytes enviados. Re-encodar entre assinar e enviar quebra a verificação do
 * outro lado mesmo com dados idênticos.
 */
final readonly class EmitWebhookEvent implements EmitsWebhookEvents
{
    public function __construct(
        private WebhookPrivateKey $privateKey = new WebhookPrivateKey,
    ) {}

    /**
     * @param  array<string, mixed>  $entityPayload
     */
    public function emit(string $subscription, string $logType, array $entityPayload): void
    {
        $resolvedSubscription = StarkbankSubscription::tryFrom($subscription);
        $resolvedType = StarkbankEventType::tryFrom($logType);

        if (!$resolvedSubscription instanceof StarkbankSubscription || !$resolvedType instanceof StarkbankEventType) {
            Log::warning('fake-starkbank.webhook: emissão descartada — vocabulário fora do que o StarkBank produz, e inventar subscription ou log type não é fidelidade', [
                'subscription' => $subscription,
                'log_type' => $logType,
                'entity_id' => $entityPayload['id'] ?? null,
            ]);

            return;
        }

        $this->handle($resolvedSubscription, $resolvedType, $entityPayload);
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  string|null  $signingKeyPem  PEM alternativo — só
     *                                      {@see EmitCorrupted} usa, para produzir uma assinatura que o
     *                                      consumidor recusa sem nenhum caminho especial no envelope.
     */
    public function handle(
        StarkbankSubscription $subscription,
        StarkbankEventType $eventType,
        array $entity,
        ?string $signingKeyPem = null,
    ): ?WebhookEmission {
        if (!$subscription->allows($eventType)) {
            Log::warning('fake-starkbank.webhook: log type fora do ciclo de vida da subscription — emitindo mesmo assim, mas o StarkBank real não produz esse par', [
                'subscription' => $subscription->value,
                'log_type' => $eventType->value,
            ]);
        }

        $now = CarbonImmutable::now()->utc()->format('Y-m-d\TH:i:s.uP');

        $envelope = new WebhookEnvelope(
            eventId: $this->numericId(),
            created: $now,
            workspaceId: (string) config('fake-starkbank.workspace.id', ''),
            subscription: $subscription,
            logId: $this->numericId(),
            logCreated: $now,
            logType: $eventType,
            entity: $entity,
        );

        $payload = WebhookPayload::fromEnvelope($envelope);

        $signature = new EcdsaSignatureSigner($signingKeyPem ?? $this->privateKey->pem())->sign($payload->rawBody);

        if ($signature === null) {
            Log::error('fake-starkbank.webhook: emissão abortada — sem chave privada legível não há o que o consumidor consiga verificar, e um webhook não assinado só viraria 401 do lado de lá', [
                'subscription' => $subscription->value,
                'log_type' => $eventType->value,
                'entity_id' => $envelope->entityId(),
            ]);

            return null;
        }

        $url = mb_trim((string) config('fake-starkbank.webhook.url', ''));

        $emission = WebhookEmission::query()->create([
            'event_id' => $envelope->eventId,
            'subscription' => $subscription,
            'event_type' => $eventType,
            'entity_id' => $envelope->entityId(),
            'url' => $url,
            'payload' => $payload,
            'signature' => $signature,
        ]);

        if ($url === '') {
            Log::info('fake-starkbank.webhook: destino não configurado — emissão gravada para inspeção e nenhum POST agendado, dev sem FAKE_STARKBANK_WEBHOOK_URL não quebra o resto do fluxo', [
                'event_id' => $emission->event_id,
                'subscription' => $subscription->value,
                'event_type' => $eventType->value,
            ]);

            return $emission;
        }

        Bus::dispatchAfterResponse(new DeliverWebhookEmission($emission->id));

        Log::info('fake-starkbank.webhook: emissão assinada e agendada para depois da resposta — POSTar agora travaria o consumidor single-thread que está no meio deste request', [
            'event_id' => $emission->event_id,
            'subscription' => $subscription->value,
            'event_type' => $eventType->value,
            'entity_id' => $emission->entity_id,
            'url' => $url,
        ]);

        return $emission;
    }

    private function numericId(): string
    {
        return NumericId::generate();
    }
}
