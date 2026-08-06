<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Http\Auth;

use phpseclib3\Crypt\EC;

/**
 * Um par ECDSA gerado na hora e jogado fora em seguida — a chave da assinatura
 * que o consumidor DEVE recusar.
 *
 * Existe como classe própria para que a chave privada real do fake nunca entre
 * no caminho do cenário adverso: quem quer uma assinatura inválida pede uma
 * chave alheia, em vez de corromper bytes de uma assinatura legítima (o que
 * produziria ora um 401, ora um base64 malformado, dependendo do byte).
 */
final readonly class ThrowawayPrivateKey
{
    public function pem(): string
    {
        return (string) EC::createKey('secp256k1');
    }
}
