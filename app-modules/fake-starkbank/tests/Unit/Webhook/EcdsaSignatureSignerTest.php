<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Http\Auth\EcdsaSignatureSigner;
use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;

uses(SignsWebhooks::class);

it('produz uma assinatura que o mecanismo do consumidor aceita', function (): void {
    $privateKey = $this->generateWebhookKeypair();

    $assinatura = new EcdsaSignatureSigner((string) $privateKey)->sign('corpo-cru');

    expect($assinatura)->toBeString()
        ->and(base64_decode((string) $assinatura, strict: true))->not->toBeFalse()
        ->and($this->verifyLikeConsumer('corpo-cru', $assinatura, (string) $privateKey->getPublicKey()))->toBeTrue();
});

it('produz assinatura que não fecha contra outra chave', function (): void {
    $assinatura = new EcdsaSignatureSigner((string) $this->generateWebhookKeypair())->sign('corpo-cru');

    $outraChave = (string) $this->generateWebhookKeypair()->getPublicKey();

    expect($this->verifyLikeConsumer('corpo-cru', $assinatura, $outraChave))->toBeFalse();
});

it('produz assinatura que não fecha sobre outro corpo', function (): void {
    // O contrato é sobre o raw body EXATO: um byte diferente já derruba.
    $privateKey = $this->generateWebhookKeypair();

    $assinatura = new EcdsaSignatureSigner((string) $privateKey)->sign('{"event":{"id":"1"}}');

    expect($this->verifyLikeConsumer('{"event":{"id":"2"}}', $assinatura, (string) $privateKey->getPublicKey()))->toBeFalse();
});

it('falha fechada sem PEM', function (): void {
    expect(new EcdsaSignatureSigner('')->sign('corpo-cru'))->toBeNull()
        ->and(new EcdsaSignatureSigner('   ')->sign('corpo-cru'))->toBeNull();
});

it('falha fechada com PEM que não é uma chave EC', function (): void {
    expect(new EcdsaSignatureSigner('-----BEGIN EC PRIVATE KEY-----lixo-----END EC PRIVATE KEY-----')->sign('corpo-cru'))->toBeNull();
});
