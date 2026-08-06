<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Exceptions;

use RuntimeException;

/**
 * O BR Code não é um EMV pagável: TLV truncado, CRC que não fecha, sem o
 * arranjo PIX no campo 26 ou com um indicador de formato desconhecido.
 *
 * Vira `invalidBrcode` (HTTP 400) no envelope de erro, com o motivo específico
 * na mensagem — nunca um preview em branco, que o consumidor leria como
 * "recebedor não verificável" e reportaria ao operador como problema de
 * cadastro, e não de payload.
 */
final class MalformedBrcodeException extends RuntimeException
{
    public static function empty(): self
    {
        return new self('Brcode is empty');
    }

    public static function notTlv(string $motivo): self
    {
        return new self(sprintf('Brcode is not a valid EMV payload: %s', $motivo));
    }

    public static function unknownFormat(string $indicador): self
    {
        return new self(sprintf('Unsupported payload format indicator: %s', $indicador));
    }

    public static function checksumMismatch(): self
    {
        return new self('Brcode CRC16 does not match');
    }

    public static function withoutPixKey(): self
    {
        return new self('Brcode carries no br.gov.bcb.pix merchant account information');
    }

    public static function unreadableAmount(string $valor): self
    {
        return new self(sprintf('Brcode transaction amount is not a decimal value: %s', $valor));
    }
}
