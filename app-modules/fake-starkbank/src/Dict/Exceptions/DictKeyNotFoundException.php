<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Dict\Exceptions;

use RuntimeException;

/**
 * Nenhuma chave com esse valor está registrada no DICT deste fake. Vira
 * `invalidDictKey` (HTTP 404) no envelope de erro — nunca uma resolução em
 * branco, que seguiria para uma transfer com beneficiário vazio em vez do
 * fail-closed que o consumidor espera.
 */
final class DictKeyNotFoundException extends RuntimeException
{
    public static function forKey(string $pixKey): self
    {
        return new self(sprintf('PIX key %s not found', $pixKey));
    }
}
