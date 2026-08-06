<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\DTOs;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;

/**
 * O parâmetro de um cenário armado — só os campos que algum desfecho usa. O
 * VO é a única fonte do shape desse JSON: `fromArray()` descarta o que não
 * reconhece, normaliza vazio para `null` e recusa valor fora do domínio do
 * campo, então uma coluna adulterada à mão nunca vira um campo meio preenchido
 * dentro do plano de execução.
 */
final readonly class ArmedScenarioPayload
{
    /**
     * A fração de fill vive em `[0, 1]`, escrita em decimal simples. Fora disso
     * não é fração nenhuma: negativa faria a wire reportar quantidade negativa
     * e acima de `1` faria a execução mover mais ledger do que o pedido pediu.
     * Notação exponencial fica fora de propósito — as contas de fill são todas
     * `bc*`, que só aceita decimal bem formado.
     */
    private const string FRACTION_PATTERN = '/^-?(\d+(\.\d*)?|\.\d+)$/';

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
            fraction: self::fillFraction($fraction),
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

    public function rawStatusOr(string $default): string
    {
        return $this->rawStatus ?? $default;
    }

    /**
     * @return numeric-string|null
     */
    private static function fillFraction(mixed $value): ?string
    {
        if (!is_string($value) || !is_numeric($value) || preg_match(self::FRACTION_PATTERN, $value) !== 1) {
            return null;
        }

        $withinRange = bccomp($value, '0', 18) >= 0 && bccomp($value, '1', 18) <= 0;

        return $withinRange ? $value : null;
    }
}
