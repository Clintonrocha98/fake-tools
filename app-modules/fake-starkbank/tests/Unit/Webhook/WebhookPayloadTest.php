<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Webhook\DTOs\WebhookEnvelope;
use He4rt\FakeStarkbank\Webhook\DTOs\WebhookPayload;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;

function payloadDeTeste(): WebhookPayload
{
    return WebhookPayload::fromEnvelope(new WebhookEnvelope(
        eventId: '6741658193526784',
        created: '2026-07-09T12:15:00.000000+00:00',
        workspaceId: '6341320293482496',
        subscription: StarkbankSubscription::Invoice,
        logId: '5099055211642880',
        logCreated: '2026-07-09T12:15:00.000000+00:00',
        logType: StarkbankEventType::Paid,
        entity: ['id' => '5155165527080960', 'amount' => 10_000],
    ));
}

it('guarda os bytes exatos do envelope serializado', function (): void {
    $payload = payloadDeTeste();

    expect($payload->rawBody)->toBe('{"event":{"id":"6741658193526784","created":"2026-07-09T12:15:00.000000+00:00","workspaceId":"6341320293482496","subscription":"invoice","log":{"id":"5099055211642880","created":"2026-07-09T12:15:00.000000+00:00","type":"paid","invoice":{"id":"5155165527080960","amount":10000}}}}');
});

it('sobrevive à ida e volta pelo formato de armazenamento sem perder um byte', function (): void {
    // É esta propriedade que faz o replay ser byte a byte: guardar a estrutura
    // e re-encodar reordenaria keys e quebraria a assinatura já emitida.
    $original = payloadDeTeste();

    $recuperado = WebhookPayload::fromArray($original->toArray());

    expect($recuperado->rawBody)->toBe($original->rawBody);
});

it('não escapa a barra de URLs dentro do corpo', function (): void {
    $payload = WebhookPayload::fromEnvelope(new WebhookEnvelope(
        eventId: '1',
        created: '2026-07-09T12:15:00.000000+00:00',
        workspaceId: '2',
        subscription: StarkbankSubscription::BrcodePayment,
        logId: '3',
        logCreated: '2026-07-09T12:15:00.000000+00:00',
        logType: StarkbankEventType::Success,
        entity: ['id' => '4', 'brcode' => 'https://brcode.test/v2/abc'],
    ));

    expect($payload->rawBody)->toContain('https://brcode.test/v2/abc');
});

it('expõe o corpo decodificado só para leitura, sem ser a fonte da wire', function (): void {
    $decodificado = payloadDeTeste()->decoded();

    expect($decodificado)->toHaveKey('event')
        ->and($decodificado['event']['log']['type'])->toBe('paid');
});

it('devolve corpo vazio quando o armazenamento não carrega rawBody', function (): void {
    expect(WebhookPayload::fromArray(['lixo' => 'de-outro-formato'])->rawBody)->toBeEmpty()
        ->and(WebhookPayload::fromArray([])->decoded())->toBeEmpty();
});
