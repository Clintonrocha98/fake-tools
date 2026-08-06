<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Brcode\Actions\AdvanceBrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

uses(SignsWebhooks::class);

/*
|--------------------------------------------------------------------------
| Avanço lazy do pagamento de BR Code
|--------------------------------------------------------------------------
|
| Dois patamares sobre a idade desde a criação, e webhook só no desfecho que o
| consumidor concilia.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankWebhook(url: null);
    config(['fake-starkbank-brcode.advance_seconds' => 60]);
});

it('não move nada antes do primeiro patamar', function (): void {
    $pagamento = BrcodePayment::factory()->create();

    $this->travel(30)->seconds();

    expect(resolve(AdvanceBrcodePaymentStatus::class)->handle($pagamento)->status)->toBe(BrcodePaymentStatus::Created)
        ->and(WebhookEmission::query()->count())->toBe(0);
});

it('atravessa o primeiro patamar em silêncio: processing não é gatilho para o consumidor', function (): void {
    $pagamento = BrcodePayment::factory()->create();

    $this->travel(61)->seconds();

    expect(resolve(AdvanceBrcodePaymentStatus::class)->handle($pagamento)->status)->toBe(BrcodePaymentStatus::Processing)
        ->and(WebhookEmission::query()->count())->toBe(0);
});

it('anuncia o evento success ao cruzar o segundo patamar', function (): void {
    $pagamento = BrcodePayment::factory()->create();

    $this->travel(121)->seconds();

    $liquidado = resolve(AdvanceBrcodePaymentStatus::class)->handle($pagamento);

    $emission = WebhookEmission::query()->firstOrFail();

    expect($liquidado->status)->toBe(BrcodePaymentStatus::Success)
        ->and($emission->subscription)->toBe(StarkbankSubscription::BrcodePayment)
        ->and($emission->event_type)->toBe(StarkbankEventType::Success)
        ->and($emission->entity_id)->toBe($pagamento->id)
        // A assimetria do wire: a subscription é `brcode-payment`, mas a entity
        // viaja sob a key `payment`.
        ->and($emission->payload->decoded())->toHaveKey('event.log.payment.tags');
});

it('emite uma vez só: a leitura seguinte encontra o estado terminal e não reanuncia', function (): void {
    $pagamento = BrcodePayment::factory()->create();

    $this->travel(121)->seconds();

    resolve(AdvanceBrcodePaymentStatus::class)->handle($pagamento);
    resolve(AdvanceBrcodePaymentStatus::class)->handle($pagamento->refresh());

    expect(WebhookEmission::query()->count())->toBe(1);
});

it('manda no webhook a entity de LEITURA, com o correlationId que o consumidor concilia', function (): void {
    $pagamento = BrcodePayment::factory()->create(['tags' => ['conversion-abc-123']]);

    $this->travel(121)->seconds();

    resolve(AdvanceBrcodePaymentStatus::class)->handle($pagamento);

    /** @var array<string, mixed> $entity */
    $entity = data_get(WebhookEmission::query()->firstOrFail()->payload->decoded(), 'event.log.payment');

    expect($entity['status'])->toBe('success')
        ->and($entity['tags'])->toBe(['conversion-abc-123'])
        ->and($entity)->not->toHaveKey('description');
});
