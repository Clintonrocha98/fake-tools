<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Exceptions;

use RuntimeException;

/**
 * Nenhuma invoice com esse id foi emitida por este fake. Vira `invalidId`
 * (HTTP 404) no envelope de erro — nunca uma resposta vazia, que o consumidor
 * leria como um brcode em branco.
 */
final class InvoiceNotFoundException extends RuntimeException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Invoice %s not found', $id));
    }
}
