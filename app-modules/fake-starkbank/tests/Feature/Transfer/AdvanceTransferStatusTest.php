<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Transfer\Actions\AdvanceTransferStatus;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

uses(SignsWebhooks::class);

/*
|--------------------------------------------------------------------------
| Avanço lazy da transfer
|--------------------------------------------------------------------------
|
| Dois patamares sobre a idade desde a criação, e webhook só nos dois desfechos
| que o TransferSettlementMapper do consumidor classifica.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankWebhook(url: null);
    config(['fake-starkbank-transfer.advance_seconds' => 60]);
});

it('não move nada antes do primeiro patamar', function (): void {
    $transfer = Transfer::factory()->create();

    $this->travel(30)->seconds();

    expect(resolve(AdvanceTransferStatus::class)->handle($transfer)->status)->toBe(TransferStatus::Created)
        ->and(WebhookEmission::query()->count())->toBe(0);
});

it('atravessa o primeiro patamar em silêncio: processing não é gatilho para o consumidor', function (): void {
    // `sending`/`processing` não são consumidos pelo TransferSettlementMapper;
    // emiti-los seria um webhook que só provoca uma releitura sem desfecho.
    $transfer = Transfer::factory()->create();

    $this->travel(61)->seconds();

    expect(resolve(AdvanceTransferStatus::class)->handle($transfer)->status)->toBe(TransferStatus::Processing)
        ->and(WebhookEmission::query()->count())->toBe(0);
});

it('anuncia o evento success ao cruzar o segundo patamar', function (): void {
    $transfer = Transfer::factory()->create();

    $this->travel(121)->seconds();

    $liquidada = resolve(AdvanceTransferStatus::class)->handle($transfer);

    $emission = WebhookEmission::query()->firstOrFail();

    expect($liquidada->status)->toBe(TransferStatus::Success)
        ->and($emission->subscription)->toBe(StarkbankSubscription::Transfer)
        ->and($emission->event_type)->toBe(StarkbankEventType::Success)
        ->and($emission->entity_id)->toBe($transfer->id)
        ->and($emission->payload->decoded())->toHaveKey('event.log.transfer.tags');
});

it('emite uma vez só: a leitura seguinte encontra o estado terminal e não reanuncia', function (): void {
    $transfer = Transfer::factory()->create();

    $this->travel(121)->seconds();

    resolve(AdvanceTransferStatus::class)->handle($transfer);
    resolve(AdvanceTransferStatus::class)->handle($transfer->refresh());

    expect(WebhookEmission::query()->count())->toBe(1);
});

it('manda no webhook a entity de LEITURA, com o correlationId que o consumidor concilia', function (): void {
    $transfer = Transfer::factory()->create(['tags' => ['payout-abc-123']]);

    $this->travel(121)->seconds();

    resolve(AdvanceTransferStatus::class)->handle($transfer);

    /** @var array<string, mixed> $entity */
    $entity = data_get(WebhookEmission::query()->firstOrFail()->payload->decoded(), 'event.log.transfer');

    expect($entity['status'])->toBe('success')
        ->and($entity['tags'])->toBe(['payout-abc-123'])
        ->and($entity)->not->toHaveKey('branchCode');
});
