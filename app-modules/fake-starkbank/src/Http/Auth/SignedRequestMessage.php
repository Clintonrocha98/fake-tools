<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Http\Auth;

/**
 * A mensagem que o StarkBank assina em cada request: `accessId:accessTime:body`
 * — dois-pontos literais, sem espaços; em GET o body é string vazia. A query
 * string NUNCA entra: ela é parte da URL, não da assinatura, e o `?cursor=` de
 * `GET /v2/workspace` é a prova viva disso.
 *
 * Espelho de `RequestSigner::stringToSign()` do consumidor. As duas composições
 * são um contrato: divergir num único caractere recusa toda assinatura.
 */
final readonly class SignedRequestMessage
{
    public static function compose(string $accessId, string $accessTime, string $rawBody): string
    {
        return $accessId.':'.$accessTime.':'.$rawBody;
    }
}
