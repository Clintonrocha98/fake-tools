<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\Exceptions;

use RuntimeException;

/**
 * Nenhuma transfer com esse id foi despachada por este fake. Vira `invalidId`
 * (HTTP 404) no envelope de erro — nunca um 200 com `transfer` vazia, que o
 * consumidor leria como `StarkbankRequestFailed::malformed()`.
 */
final class TransferNotFoundException extends RuntimeException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Transfer %s not found', $id));
    }
}
