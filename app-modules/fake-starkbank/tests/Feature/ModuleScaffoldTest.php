<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Webhook\Actions\EmitWebhookEvent;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;

it('registra o provider e resolve as configs core', function (): void {
    expect(config('fake-starkbank.client.access_id'))->not->toBeNull();
    expect(config('fake-starkbank.workspace.id'))->toBe('6341320293482496');
    expect(app()->bound(EmitsWebhookEvents::class))->toBeTrue();
});

it('resolve o contrato de webhook no emissor assinado real', function (): void {
    expect(resolve(EmitsWebhookEvents::class))->toBeInstanceOf(EmitWebhookEvent::class);
});
