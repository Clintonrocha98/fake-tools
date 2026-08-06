<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Tests\Support;

use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\EC\PrivateKey;
use phpseclib3\Crypt\EC\PublicKey;
use phpseclib3\Crypt\PublicKeyLoader;
use Throwable;

/**
 * O par de webhook (fake → consumidor), gerado em runtime a cada teste —
 * nenhuma chave fixa comitada. Distinto do par de cliente do
 * {@see SignsRequests}: são os dois sentidos do contrato, e compartilhar um par
 * esconderia justamente o erro de trocar um pelo outro.
 *
 * `verifyLikeConsumer()` é o mecanismo do `DigitalSignatureVerifier` do
 * monolito, não uma reimplementação: phpseclib3, base64 estrito, `withHash`
 * SHA-256 e `verify()` sobre o raw body. Verificar com o código do próprio fake
 * provaria só que ele concorda consigo mesmo.
 */
trait SignsWebhooks
{
    /**
     * Constante de trait só é acessível de dentro dela (PHP 8.2+) — quem
     * escreve teste usa {@see webhookUrl()}.
     */
    private const string DEFAULT_WEBHOOK_URL = 'https://consumidor.test/webhooks/starkbank';

    private ?PrivateKey $webhookPrivateKey = null;

    /**
     * O destino de webhook usado nos testes — o `POST /webhooks/starkbank` do
     * consumidor.
     */
    protected function webhookUrl(): string
    {
        return self::DEFAULT_WEBHOOK_URL;
    }

    /**
     * Gera o par de webhook e configura o fake para assinar com ele, apontando
     * a emissão para `$url` (`null` = destino não configurado).
     */
    protected function configureFakeStarkbankWebhook(?string $url = self::DEFAULT_WEBHOOK_URL): PrivateKey
    {
        $this->webhookPrivateKey = $this->generateWebhookKeypair();

        config([
            'fake-starkbank.webhook.url' => $url,
            'fake-starkbank.webhook.private_key' => (string) $this->webhookPrivateKey,
            'fake-starkbank.webhook.private_key_path' => null,
            'fake-starkbank.webhook.timeout_seconds' => 5,
        ]);

        return $this->webhookPrivateKey;
    }

    protected function generateWebhookKeypair(): PrivateKey
    {
        $key = EC::createKey('secp256k1');

        expect($key)->toBeInstanceOf(PrivateKey::class);

        /** @var PrivateKey $key */
        return $key;
    }

    /**
     * O PEM público do par de webhook em uso — o mesmo arquivo que o consumidor
     * lê em `webhook_public_key_path`.
     */
    protected function webhookPublicKeyPem(): string
    {
        $privateKey = $this->webhookPrivateKey ?? $this->configureFakeStarkbankWebhook();

        return (string) $privateKey->getPublicKey();
    }

    /**
     * Verificação idêntica à do `DigitalSignatureVerifier` do consumidor.
     */
    protected function verifyLikeConsumer(string $rawBody, ?string $signature, ?string $publicKeyPem = null): bool
    {
        $publicKeyPem ??= $this->webhookPublicKeyPem();

        if ($signature === null || $signature === '') {
            return false;
        }

        $decoded = base64_decode($signature, strict: true);

        if ($decoded === false || $decoded === '') {
            return false;
        }

        try {
            $key = PublicKeyLoader::load($publicKeyPem);

            if (!$key instanceof PublicKey) {
                return false;
            }

            $verifyingKey = $key->withHash('sha256');

            if (!$verifyingKey instanceof PublicKey) {
                return false;
            }

            return $verifyingKey->verify($rawBody, $decoded) === true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * A entity de uma invoice paga, no shape que o `GET /v2/invoice/{id}`
     * serve — o mesmo conjunto de campos do fixture do consumidor.
     *
     * @return array<string, mixed>
     */
    protected function invoiceEntity(string $id = '5155165527080960', string $status = 'paid'): array
    {
        return [
            'id' => $id,
            'amount' => 10_000,
            'status' => $status,
            'tags' => ['deposit-abc-123'],
        ];
    }
}
