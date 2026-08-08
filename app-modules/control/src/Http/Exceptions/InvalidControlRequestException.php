<?php

declare(strict_types=1);

namespace He4rt\Control\Http\Exceptions;

use BackedEnum;
use He4rt\Control\Http\ControlErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Perna, desfecho, switch ou status fora do vocabulário do fake — 422, sempre
 * com a lista do que vale. Um 500 aqui deixaria o dev adivinhando o dialeto.
 */
final class InvalidControlRequestException extends RuntimeException
{
    /**
     * @param  list<string>  $valid
     */
    private function __construct(string $message, public readonly array $valid)
    {
        parent::__construct($message);
    }

    /**
     * @param  list<string>  $pernas
     */
    public static function unknownLeg(string $leg, array $pernas): self
    {
        return new self(sprintf('Perna desconhecida "%s".', $leg), $pernas);
    }

    /**
     * @param  list<string>  $outcomes
     */
    public static function outcomeNotInLeg(string $outcome, string $leg, array $outcomes): self
    {
        return new self(
            sprintf('O desfecho "%s" não pertence à perna "%s".', $outcome, $leg),
            $outcomes,
        );
    }

    /**
     * @param  list<string>  $switches
     */
    public static function unknownSwitch(string $switch, array $switches): self
    {
        return new self(sprintf('Switch desconhecido "%s".', $switch), $switches);
    }

    /**
     * @param  list<BackedEnum>  $casos
     */
    public static function unknownStatus(string $status, array $casos): self
    {
        return new self(
            sprintf('Status desconhecido "%s".', $status),
            array_map(static fn (BackedEnum $caso): string => (string) $caso->value, $casos),
        );
    }

    /**
     * 422 e nunca 500: o dialeto do fake é o que o dev precisa descobrir aqui,
     * e a lista do que vale viaja junto do erro.
     */
    public function render(Request $request): JsonResponse
    {
        return ControlErrorResponse::make($this->getMessage(), 422, $this->valid);
    }
}
