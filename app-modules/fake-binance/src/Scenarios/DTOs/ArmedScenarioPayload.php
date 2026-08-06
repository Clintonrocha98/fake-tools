<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\DTOs;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;

/**
 * O parâmetro de um cenário armado — só os campos que algum desfecho usa. O
 * VO é a única fonte do shape desse JSON: `fromArray()` descarta o que não
 * reconhece e normaliza vazio para `null`, então uma coluna adulterada à mão
 * nunca vira um campo meio preenchido dentro do plano de execução.
 */
final readonly class ArmedScenarioPayload
{
    /**
     * @param  numeric-string|null  $fraction  Fração do fill (desfecho parcial)
     * @param  int|null  $errorCode  Valor de {@see BinanceErrorCode} (desfecho de recusa)
     * @param  string|null  $rawStatus  Vocabulário de wire arbitrário
     * @param  string|null  $reason  Motivo textual (campo `info` do saque)
     */
    public function __construct(
        public ?string $fraction = null,
        public ?int $errorCode = null,
        public ?string $rawStatus = null,
        public ?string $reason = null,
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
        $fraction = $payload['fraction'] ?? null;
        $errorCode = $payload['errorCode'] ?? null;
        $rawStatus = $payload['rawStatus'] ?? null;
        $reason = $payload['reason'] ?? null;

        return new self(
            fraction: is_string($fraction) && is_numeric($fraction) ? $fraction : null,
            errorCode: is_numeric($errorCode) ? (int) $errorCode : null,
            rawStatus: is_string($rawStatus) && $rawStatus !== '' ? $rawStatus : null,
            reason: is_string($reason) && $reason !== '' ? $reason : null,
        );
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return array_filter([
            'fraction' => $this->fraction,
            'errorCode' => $this->errorCode,
            'rawStatus' => $this->rawStatus,
            'reason' => $this->reason,
        ], static fn (int|string|null $value): bool => $value !== null);
    }

    public function binanceErrorCode(): ?BinanceErrorCode
    {
        return $this->errorCode !== null ? BinanceErrorCode::tryFrom($this->errorCode) : null;
    }

    /**
     * @param  numeric-string  $default
     * @return numeric-string
     */
    public function fractionOr(string $default): string
    {
        return $this->fraction ?? $default;
    }
}
