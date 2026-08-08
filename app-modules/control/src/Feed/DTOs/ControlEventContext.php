<?php

declare(strict_types=1);

namespace He4rt\Control\Feed\DTOs;

/**
 * O contexto estruturado que a Action passou ao canal de log. É genuinamente
 * polimórfico — cada linha dos canais `binance`/`starkbank` traz as chaves que
 * fazem sentido para ela —, e é justamente por isso que ele entra aqui em vez
 * de num cast solto: `json_decode` devolve `array<array-key, mixed>` e nunca a
 * sua forma, então a fronteira é tratada uma vez só, e quem lê acessa por
 * método tipado em vez de indexar um `mixed`.
 */
final readonly class ControlEventContext
{
    /**
     * @param  array<string, scalar|array<array-key, mixed>|null>  $values
     */
    public function __construct(public array $values = []) {}

    public static function empty(): self
    {
        return new self;
    }

    /**
     * Só chave string e valor serializável entram: um record do Monolog pode
     * carregar objeto ou closure no contexto, e o que não sobrevive ao
     * `json_encode` não pertence a uma coluna jsonb.
     *
     * @param  array<array-key, mixed>  $context
     */
    public static function fromArray(array $context): self
    {
        $values = [];

        foreach ($context as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if ($value === null || is_scalar($value)) {
                $values[$key] = $value;

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $value;
            }
        }

        return new self($values);
    }

    /**
     * @return array<string, scalar|array<array-key, mixed>|null>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function string(string $key, ?string $default = null): ?string
    {
        $value = $this->values[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function integer(string $key, ?int $default = null): ?int
    {
        $value = $this->values[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }
}
