<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Tests\Support;

use Illuminate\Testing\TestResponse;
use JsonException;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\EC\PrivateKey;

/**
 * Assina requests de teste exatamente como o `StarkbankConnector` do monolito
 * consumidor assina: ECDSA secp256k1 + SHA-256 sobre `accessId:accessTime:body`,
 * DER em base64 no header `Access-Signature`.
 *
 * O par de chaves é GERADO EM RUNTIME a cada teste — nenhuma chave fixa é
 * comitada aqui. Reutilizável pelos tickets seguintes, que também precisam de
 * requests assinados batendo com o fake.
 */
trait SignsRequests
{
    public const string ACCESS_ID = 'project/6341320293482496';

    private ?PrivateKey $clientPrivateKey = null;

    /**
     * Gera um par EC novo e configura o fake para aceitá-lo: `access_id`
     * conhecido e a chave pública correspondente inline. Chame no início de
     * cada teste que assina requests.
     */
    protected function configureFakeStarkbankClient(?PrivateKey $privateKey = null): PrivateKey
    {
        $this->clientPrivateKey = $privateKey ?? $this->generateKeypair();

        config([
            'fake-starkbank.client.access_id' => self::ACCESS_ID,
            'fake-starkbank.client.public_key' => (string) $this->clientPrivateKey->getPublicKey(),
            'fake-starkbank.client.public_key_path' => null,
        ]);

        return $this->clientPrivateKey;
    }

    protected function generateKeypair(): PrivateKey
    {
        $key = EC::createKey('secp256k1');

        expect($key)->toBeInstanceOf(PrivateKey::class);

        /** @var PrivateKey $key */
        return $key;
    }

    /**
     * Os três headers de assinatura de um request. `$body` precisa ser
     * exatamente o corpo que vai na wire (string vazia em GET) — é sobre ele
     * que a mensagem é composta.
     *
     * @return array<string, string>
     */
    protected function signedHeaders(
        string $body = '',
        ?int $accessTime = null,
        ?string $accessId = null,
        ?PrivateKey $privateKey = null,
    ): array {
        $accessId ??= self::ACCESS_ID;
        $accessTime ??= now()->getTimestamp();
        $privateKey ??= $this->clientPrivateKey ?? $this->configureFakeStarkbankClient();

        $message = $accessId.':'.$accessTime.':'.$body;

        return [
            'Access-Id' => $accessId,
            'Access-Time' => (string) $accessTime,
            'Access-Signature' => base64_encode((string) $privateKey->withHash('sha256')->sign($message)),
        ];
    }

    /**
     * GET com corpo VAZIO, que é o que o consumidor manda na wire. Nunca use
     * `getJson()` num request assinado: ele serializa `[]` no corpo, a mensagem
     * assinada passa a ser `accessId:accessTime:[]` e nada verifica.
     *
     * @param  array<string, string>  $headers
     */
    protected function getSigned(string $uri, array $headers = []): TestResponse
    {
        return $this->get($uri, $headers + ['Accept' => 'application/json']);
    }

    /**
     * POST assinado sobre os bytes EXATOS do corpo. `postJson()` não serve pelo
     * mesmo motivo que `getJson()`: quem assina precisa do JSON já serializado,
     * e reencodá-lo depois produz outra mensagem.
     *
     * @param  array<array-key, mixed>  $body
     * @param  array<string, string>|null  $headers  headers de assinatura já prontos — só
     *                                               quem quer assinar errado (chave alheia, corpo divergente) passa isto
     *
     * @throws JsonException
     */
    protected function postSigned(string $uri, array $body = [], ?array $headers = null): TestResponse
    {
        $content = $this->jsonBody($body);
        $headers ??= $this->signedHeaders($content);

        return $this->call(
            method: 'POST',
            uri: $uri,
            server: $this->transformHeadersToServerVars($headers + [
                'CONTENT_TYPE' => 'application/json',
                'Accept' => 'application/json',
            ]),
            content: $content,
        );
    }

    /**
     * @param  array<array-key, mixed>  $body
     *
     * @throws JsonException
     */
    protected function jsonBody(array $body): string
    {
        return json_encode($body, JSON_THROW_ON_ERROR);
    }
}
