<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;

it('registra o provider e resolve as configs core', function (): void {
    expect(config('fake-starkbank.client.access_id'))->not->toBeNull();
    expect(config('fake-starkbank.workspace.id'))->toBe('6341320293482496');
    expect(app()->bound(EmitsWebhookEvents::class))->toBeTrue();
});

it('resolve o contrato de webhook no emissor nulo por default', function (): void {
    expect(resolve(EmitsWebhookEvents::class))->toBeInstanceOf(NullWebhookEmitter::class);
});
