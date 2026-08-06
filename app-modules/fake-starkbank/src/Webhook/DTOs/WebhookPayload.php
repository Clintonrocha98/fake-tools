<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\DTOs;

use JsonException;

/**
 * O body EXATO que foi assinado e POSTado, guardado como sequência de bytes —
 * não como estrutura. Essa é a razão de o VO existir em vez de um jsonb solto:
 * jsonb normaliza espaçamento e reordena keys, e um `json_encode` diferente do
 * que foi assinado quebra a verificação do consumidor mesmo com dados
 * idênticos. Guardando a string, `ReplayEmission` reenvia byte a byte.
 *
 * `decoded()` é conveniência de leitura (painel, testes de shape) e nunca a
 * fonte do que vai na wire — quem entrega usa {@see rawBody}.
 */
final readonly class WebhookPayload
{
    private function __construct(public string $rawBody) {}

    public static function fromRawBody(string $rawBody): self
    {
        return new self($rawBody);
    }

    /**
     * Serializa o envelope UMA vez: os bytes que saem daqui são os que se
     * assina e os que se envia.
     *
     * @throws JsonException
     */
    public static function fromEnvelope(WebhookEnvelope $envelope): self
    {
        return new self(json_encode(
            $envelope->jsonSerialize(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $rawBody = $payload['rawBody'] ?? null;

        return new self(is_string($rawBody) ? $rawBody : '');
    }

    /**
     * @return array{rawBody: string}
     */
    public function toArray(): array
    {
        return ['rawBody' => $this->rawBody];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function decoded(): array
    {
        $decoded = json_decode($this->rawBody, associative: true);

        return is_array($decoded) ? $decoded : [];
    }
}
