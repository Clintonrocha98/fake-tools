<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Exceptions;

use RuntimeException;

/**
 * Nenhum pagamento com esse id foi criado por este fake. Vira `invalidId`
 * (HTTP 404) no envelope de erro — nunca um 200 com `payment` vazio, que o
 * consumidor leria como resposta malformada.
 */
final class BrcodePaymentNotFoundException extends RuntimeException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Brcode payment %s not found', $id));
    }
}
