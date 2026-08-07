<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\Actions;

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Http\Auth\EcdsaSignatureSigner;
use He4rt\FakeStarkbank\Http\Auth\ThrowawayPrivateKey;
use He4rt\FakeStarkbank\Http\Auth\WebhookPrivateKey;
use He4rt\FakeStarkbank\Support\NumericId;
use He4rt\FakeStarkbank\Support\StarkbankLog;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\DTOs\WebhookEmissionPlan;
use He4rt\FakeStarkbank\Webhook\DTOs\WebhookEnvelope;
use He4rt\FakeStarkbank\Webhook\DTOs\WebhookPayload;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use He4rt\FakeStarkbank\Webhook\Jobs\DeliverWebhookEmission;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Date;

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
 *
 * Esta é a perna POR EVENTO do subsistema de cenários: cada emissão consulta e
 * consome o armado de `PixLeg::StarkbankWebhook`
 * ({@see PlanNextWebhookEmission}), então "armar" vale para o próximo evento,
 * não para a próxima invoice.
 */
final readonly class EmitWebhookEvent implements EmitsWebhookEvents
{
    public function __construct(
        private WebhookPrivateKey $privateKey = new WebhookPrivateKey,
        private PlanNextWebhookEmission $planNext = new PlanNextWebhookEmission,
        private ThrowawayPrivateKey $throwawayKey = new ThrowawayPrivateKey,
    ) {}

    /**
     * @param  array<string, mixed>  $entityPayload
     */
    public function emit(string $subscription, string $logType, array $entityPayload, ?string $reason = null): void
    {
        $resolvedSubscription = StarkbankSubscription::tryFrom($subscription);
        $resolvedType = StarkbankEventType::tryFrom($logType);

        if (!$resolvedSubscription instanceof StarkbankSubscription || !$resolvedType instanceof StarkbankEventType) {
            StarkbankLog::warning('fake-starkbank.webhook: emissão descartada — vocabulário fora do que o StarkBank produz, e inventar subscription ou log type não é fidelidade', [
                'subscription' => $subscription,
                'log_type' => $logType,
                'entity_id' => $entityPayload['id'] ?? null,
            ]);

            return;
        }

        $this->handle($resolvedSubscription, $resolvedType, $entityPayload, reason: $reason);
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
        ?string $reason = null,
    ): WebhookEmission {
        if (!$subscription->allows($eventType)) {
            StarkbankLog::warning('fake-starkbank.webhook: log type fora do ciclo de vida da subscription — emitindo mesmo assim, mas o StarkBank real não produz esse par', [
                'subscription' => $subscription->value,
                'log_type' => $eventType->value,
            ]);
        }

        $plan = $this->planFor($signingKeyPem);

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
            logReason: $reason,
        );

        $payload = WebhookPayload::fromEnvelope($envelope);

        $signature = new EcdsaSignatureSigner($this->signingPem($signingKeyPem, $plan))->sign($payload->rawBody);

        $url = mb_trim((string) config('fake-starkbank.webhook.url', ''));

        // Sem chave legível a emissão é GRAVADA assim mesmo, sem assinatura e
        // com o motivo à vista — simetria com o destino não configurado logo
        // abaixo. Abortar antes do insert faria a transição de estado acontecer
        // no banco sem deixar rastro na única trilha que esta perna tem, e não
        // sobraria linha para o replay reenviar depois de configurar o PEM.
        $emission = WebhookEmission::query()->create([
            'event_id' => $envelope->eventId,
            'subscription' => $subscription,
            'event_type' => $eventType,
            'entity_id' => $envelope->entityId(),
            'url' => $url,
            'payload' => $payload,
            'signature' => $signature ?? '',
            'failed_reason' => $signature === null ? WebhookEmission::UNSIGNED_REASON : null,
            'held_at' => $plan->held ? Date::now() : null,
        ]);

        if ($signature === null) {
            StarkbankLog::error('fake-starkbank.webhook: emissão gravada SEM assinatura — sem chave privada legível não há o que o consumidor verifique, mas a linha fica na fila para o flush assiná-la e entregá-la quando o PEM aparecer', [
                'event_id' => $emission->event_id,
                'subscription' => $subscription->value,
                'log_type' => $eventType->value,
                'entity_id' => $envelope->entityId(),
            ]);

            return $emission;
        }

        if ($plan->corruptSignature) {
            StarkbankLog::warning('fake-starkbank.webhook: emissão assinada com chave alheia por cenário armado — o consumidor deve recusar com 401 e não persistir nada', [
                'event_id' => $emission->event_id,
                'subscription' => $subscription->value,
                'event_type' => $eventType->value,
            ]);
        }

        if ($plan->held) {
            StarkbankLog::warning('fake-starkbank.webhook: emissão represada por cenário armado — nada é POSTado até um operador liberar, e é a linha da emissão, não o cenário, que carrega essa espera', [
                'event_id' => $emission->event_id,
                'subscription' => $subscription->value,
                'event_type' => $eventType->value,
            ]);

            return $emission;
        }

        if ($url === '') {
            StarkbankLog::info('fake-starkbank.webhook: destino não configurado — emissão gravada para inspeção e nenhum POST agendado, dev sem FAKE_STARKBANK_WEBHOOK_URL não quebra o resto do fluxo', [
                'event_id' => $emission->event_id,
                'subscription' => $subscription->value,
                'event_type' => $eventType->value,
            ]);

            return $emission;
        }

        Bus::dispatchAfterResponse(new DeliverWebhookEmission($emission->id));

        if ($plan->duplicate) {
            // MESMO event.id, duas entregas: é exatamente a repetição que a
            // idempotência do consumidor (`firstOrCreate(event_id)`) precisa
            // reconhecer. Gerar um segundo id seria outro evento, não uma
            // duplicata.
            Bus::dispatchAfterResponse(new DeliverWebhookEmission($emission->id));

            StarkbankLog::warning('fake-starkbank.webhook: entrega duplicada por cenário armado — o mesmo event.id sai duas vezes para exercitar a idempotência do consumidor', [
                'event_id' => $emission->event_id,
                'subscription' => $subscription->value,
                'event_type' => $eventType->value,
            ]);
        }

        StarkbankLog::info('fake-starkbank.webhook: emissão assinada e agendada para depois da resposta — POSTar agora travaria o consumidor single-thread que está no meio deste request', [
            'event_id' => $emission->event_id,
            'subscription' => $subscription->value,
            'event_type' => $eventType->value,
            'entity_id' => $emission->entity_id,
            'url' => $url,
        ]);

        return $emission;
    }

    /**
     * A emissão com PEM explícito ({@see EmitCorrupted}, disparada à mão pelo
     * operador) NÃO olha o cenário armado — nem para a chave, nem para
     * `held`/`duplicate`.
     *
     * O armado espera o próximo evento REAL: gastá-lo num clique faria o
     * `HoldNext` sumir sem ter valido para o evento que o operador queria
     * observar, e faria o envelope corrompido nascer represado — o oposto exato
     * do 401 que esse cenário existe para exercitar.
     */
    private function planFor(?string $signingKeyPem): WebhookEmissionPlan
    {
        return $signingKeyPem === null
            ? $this->planNext->handle()
            : WebhookEmissionPlan::neutral();
    }

    private function signingPem(?string $signingKeyPem, WebhookEmissionPlan $plan): string
    {
        if ($signingKeyPem !== null) {
            return $signingKeyPem;
        }

        return $plan->corruptSignature ? $this->throwawayKey->pem() : $this->privateKey->pem();
    }

    private function numericId(): string
    {
        return NumericId::generate();
    }
}
