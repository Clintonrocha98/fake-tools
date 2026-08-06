<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Http\Auth;

use phpseclib3\Crypt\EC\PublicKey;
use phpseclib3\Crypt\PublicKeyLoader;
use Throwable;

/**
 * Verifica DE VERDADE a Access-Signature: ECDSA secp256k1 sobre SHA-256, DER
 * decodificado de base64, contra a chave pública do cliente. Mesmo stack
 * (phpseclib3) e mesmo esquema do `EcdsaSigner` que produz a assinatura no
 * consumidor — se o fake aceitasse sem verificar, a perna de autenticação do
 * monolito nunca seria exercitada em dev.
 *
 * Falha FECHADA: PEM vazio, assinatura ausente, base64 corrompido, chave que
 * não é EC ou qualquer erro de decodificação devolvem `false`.
 */
final readonly class EcdsaSignatureVerifier
{
    public function __construct(private string $publicKeyPem) {}

    public function verify(string $message, ?string $signature): bool
    {
        if (mb_trim($this->publicKeyPem) === '') {
            return false;
        }

        if ($signature === null || $signature === '') {
            return false;
        }

        $decoded = base64_decode($signature, strict: true);

        if ($decoded === false || $decoded === '') {
            return false;
        }

        try {
            $key = PublicKeyLoader::load($this->publicKeyPem);

            if (!$key instanceof PublicKey) {
                return false;
            }

            // withHash() clona a chave com SHA-256 (o esquema do StarkBank);
            // phpseclib tipa o retorno de forma larga, então re-estreite antes
            // de verificar.
            $verifyingKey = $key->withHash('sha256');

            if (!$verifyingKey instanceof PublicKey) {
                return false;
            }

            return $verifyingKey->verify($message, $decoded) === true;
        } catch (Throwable) {
            return false;
        }
    }
}
