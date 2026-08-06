<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Exceptions;

use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;
use RuntimeException;

/**
 * O `POST /v2/brcode-payment` foi recusado ANTES de virar registro. Cada
 * named constructor é um dos caminhos de recusa que o consumidor já apanhou ao
 * vivo do provedor — as mensagens são LITERAIS de propósito: é por elas que o
 * operador reconhece, no log do monolito, que o fake recusou pelo mesmo motivo
 * que o StarkBank recusaria.
 *
 * A exception carrega o próprio código de wire porque quem recusa sabe a causa;
 * o controller só traduz para o envelope. O nome é `errorCode`, e não `code`:
 * `Exception::$code` já existe e é um int não-readonly.
 */
final class BrcodePaymentRefusedException extends RuntimeException
{
    private function __construct(
        public readonly StarkbankErrorCode $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * A correlação viaja só em `tags`: o endpoint de brcode-payment é o único
     * do contrato que RECUSA `externalId`, e o consumidor conta com essa recusa
     * para não voltar a mandá-lo.
     */
    public static function unknownExternalId(): self
    {
        return new self(StarkbankErrorCode::InvalidJson, 'Unknown parameters in payment: externalId');
    }

    public static function missingDescription(): self
    {
        return new self(StarkbankErrorCode::InvalidJson, 'Missing parameters in payment: description');
    }

    public static function taxIdMismatch(string $doCorpo, string $doBrcode): self
    {
        return new self(
            StarkbankErrorCode::InvalidTaxId,
            sprintf('Payment taxId %s does not match the brcode receiver %s', $doCorpo, $doBrcode),
        );
    }

    public static function amountMismatch(int $doCorpo, int $doBrcode): self
    {
        return new self(
            StarkbankErrorCode::InvalidAmount,
            sprintf('Payment amount %d does not match the brcode amount %d', $doCorpo, $doBrcode),
        );
    }
}
