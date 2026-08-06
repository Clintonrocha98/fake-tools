<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Support;

use Carbon\CarbonImmutable;
use JsonException;
use Stringable;
use Throwable;

/**
 * O cursor opaco das listagens do fake (`after` nas listas de invoice,
 * transfer e brcode-payment). Guarda a posição exata da última linha servida —
 * `(created, id)`, o par que ordena a página — para que a página seguinte
 * continue de onde a anterior parou mesmo com linhas novas chegando no meio.
 *
 * Opaco de propósito: o consumidor só o devolve como recebeu. Um valor que não
 * decodifica NUNCA é erro — o mesmo parâmetro `after` também aceita uma data
 * ISO-8601 (é assim que `starkbank:poll-extrato --after=` o usa), e quem chama
 * decide o que fazer com o `null`.
 */
final readonly class PageCursor implements Stringable
{
    private function __construct(
        public CarbonImmutable $createdAt,
        public string $id,
    ) {}

    public function __toString(): string
    {
        return $this->encode();
    }

    public static function at(CarbonImmutable $createdAt, string $id): self
    {
        return new self($createdAt, $id);
    }

    public static function tryDecode(string $raw): ?self
    {
        $decoded = base64_decode(strtr($raw, '-_', '+/'), strict: true);

        if ($decoded === false) {
            return null;
        }

        try {
            /** @var array<array-key, mixed> $payload */
            $payload = (array) json_decode($decoded, associative: true, flags: JSON_THROW_ON_ERROR);

            $createdAt = $payload['created'] ?? null;
            $id = $payload['id'] ?? null;

            if (!is_string($createdAt) || !is_string($id) || $id === '') {
                return null;
            }

            return new self(CarbonImmutable::parse($createdAt), $id);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @throws JsonException
     */
    public function encode(): string
    {
        $payload = json_encode([
            'created' => $this->createdAt->utc()->format('Y-m-d\TH:i:s.uP'),
            'id' => $this->id,
        ], JSON_THROW_ON_ERROR);

        return mb_rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }
}
