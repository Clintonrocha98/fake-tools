<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\DTOs;

/**
 * O parâmetro de um cenário armado da malha PIX — só os dois campos que algum
 * desfecho usa. O VO é a única fonte do shape desse JSON: `fromArray()`
 * descarta o que não reconhece, normaliza vazio para `null` e recusa valor fora
 * do domínio do campo, então uma coluna adulterada à mão nunca vira um campo
 * meio preenchido dentro do plano de execução.
 */
final readonly class PixScenarioPayload
{
    /**
     * O atraso extra vive em `[0, MAX_EXTRA_SECONDS]`. Negativo adiantaria o
     * relógio em vez de atrasá-lo, e um valor absurdo congelaria a invoice sem
     * o operador perceber que foi ele quem a congelou.
     */
    private const int MAX_EXTRA_SECONDS = 86_400;

    /**
     * @param  string|null  $reason  Motivo textual da recusa (viaja no log do webhook)
     * @param  int|null  $extraSeconds  Segundos somados ao relógio do avanço lazy
     */
    public function __construct(
        public ?string $reason = null,
        public ?int $extraSeconds = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $reason = $payload['reason'] ?? null;

        return new self(
            reason: is_string($reason) && $reason !== '' ? $reason : null,
            extraSeconds: self::extraSeconds($payload['extraSeconds'] ?? null),
        );
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return array_filter([
            'reason' => $this->reason,
            'extraSeconds' => $this->extraSeconds,
        ], static fn (int|string|null $value): bool => $value !== null);
    }

    public function reasonOr(string $default): string
    {
        return $this->reason ?? $default;
    }

    public function extraSecondsOr(int $default): int
    {
        return $this->extraSeconds ?? $default;
    }

    private static function extraSeconds(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $seconds = (int) $value;

        return $seconds >= 0 && $seconds <= self::MAX_EXTRA_SECONDS ? $seconds : null;
    }
}
