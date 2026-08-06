<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Dict\Support;

/**
 * Os blobs que o DICT devolve em `branchCode` e `accountNumber`: no StarkBank
 * real são a agência e a conta CRIPTOGRAFADAS, no formato `*<base64>=`, feitas
 * para serem ecoadas verbatim no `POST /v2/transfer` e nunca parseadas.
 *
 * O fake não tem o que criptografar — a agência e a conta do beneficiário não
 * existem aqui —, então o blob é derivado da própria chave PIX por hash: opaco
 * como o real e DETERMINÍSTICO, para que reseed e releitura devolvam o mesmo
 * valor e o operador possa comparar duas execuções.
 */
final readonly class OpaqueAccountBlob
{
    public static function forBranch(string $pixKey): string
    {
        return self::blob('branch:'.$pixKey, 'sha256');
    }

    public static function forAccount(string $pixKey): string
    {
        return self::blob('account:'.$pixKey, 'sha512');
    }

    private static function blob(string $seed, string $algorithm): string
    {
        return '*'.base64_encode(hash($algorithm, $seed, binary: true));
    }
}
