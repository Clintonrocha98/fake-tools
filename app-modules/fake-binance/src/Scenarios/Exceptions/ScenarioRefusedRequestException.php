<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Exceptions;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use RuntimeException;

/**
 * Um cenário armado mandou recusar o pedido antes de qualquer efeito. Carrega o
 * código a devolver; quem mapeia para o envelope da família é o controller,
 * como já acontece com as demais recusas da perna.
 *
 * A propriedade chama-se `errorCode`, não `code`: `Exception::$code` já existe,
 * sem tipo e sem `readonly`, e PHP proíbe uma subclasse de redeclarar essa
 * propriedade herdada com tipo ou `readonly` — só um nome distinto evita o
 * conflito.
 */
final class ScenarioRefusedRequestException extends RuntimeException
{
    private function __construct(public readonly BinanceErrorCode $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public static function withCode(BinanceErrorCode $code): self
    {
        return new self($code, $code->defaultMessage());
    }
}
